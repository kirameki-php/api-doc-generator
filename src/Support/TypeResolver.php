<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Support;

use Closure;
use Kirameki\ApiDocGenerator\Components\ClassInfo;
use Kirameki\ApiDocGenerator\Components\InterfaceInfo;
use Kirameki\ApiDocGenerator\Components\TemplateInfo;
use Kirameki\ApiDocGenerator\Components\TraitInfo;
use Kirameki\ApiDocGenerator\Types\CallableVarType;
use Kirameki\ApiDocGenerator\Types\ConditionalVarType;
use Kirameki\ApiDocGenerator\Types\IntersectionVarType;
use Kirameki\ApiDocGenerator\Types\NamedVarType;
use Kirameki\ApiDocGenerator\Types\ParameterVarType;
use Kirameki\ApiDocGenerator\Types\StructureVarType;
use Kirameki\ApiDocGenerator\Types\TemplateVarType;
use Kirameki\ApiDocGenerator\Types\UnionVarType;
use Kirameki\ApiDocGenerator\Types\VarType;
use Kirameki\Core\Exceptions\UnreachableException;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprIntegerNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\CallableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ConditionalTypeForParameterNode;
use PHPStan\PhpDocParser\Ast\Type\ConstTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IntersectionTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ThisTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use ReflectionClass;
use ReflectionEnum;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Stringable;
use UnitEnum;
use function array_map;
use function array_values;
use function assert;
use function class_exists;
use function enum_exists;
use function in_array;
use function interface_exists;
use function is_string;
use function ksort;
use function trait_exists;
use const SORT_NATURAL;

class TypeResolver
{
    /**
     * @var PhpDoc
     */
    protected PhpDoc $phpDoc {
        get => $this->phpDoc ??= $this->docParser->parse((string) $this->reflection->getDocComment());
    }

    /**
     * @param ReflectionClass<object>|ReflectionClass<UnitEnum> $reflection
     * @param PhpFile $file
     * @param CommentParser $docParser
     * @param UrlResolver $urlResolver
     */
    public function __construct(
        protected readonly ReflectionClass $reflection,
        protected readonly PhpFile $file,
        protected readonly CommentParser $docParser,
        protected readonly UrlResolver $urlResolver,
    ) {
    }

    /**
     * @return list<TemplateInfo>
     */
    public function resolveTemplates(): array
    {
        $templates = [];
        foreach ($this->phpDoc->templates as $tag) {
            $templates[] = new TemplateInfo(
                $tag->name,
                $tag->bound
                    ? $this->resolveFromNode($tag->bound)
                    : null,
                $tag->default
                    ? $this->resolveFromNode($tag->default)
                    : null,
            );
        }
        return $templates;
    }

    /**
     * @return VarType|null
     */
    public function resolveParent(): ?VarType
    {
        $node = $this->phpDoc->extends?->type;
        if ($node !== null) {
            return $this->resolveFromNode($node);
        }
        $reflection = $this->file->reflection->getParentClass();
        if ($reflection === false) {
            return null;
        }
        return $this->instantiateClassInfo($reflection)->toType();
    }

    /**
     * @return list<VarType>
     */
    public function resolveInterfaces(): array
    {
        $types = [];
        foreach ($this->phpDoc->implements as $tag) {
            $types[$tag->type->type->name] = $this->resolveFromNode($tag->type);
        }
        foreach ($this->file->implements as $if) {
            $reflection = new ReflectionClass($if);
            $types[$reflection->getName()] ??= $this->instantiateClassInfo($reflection)->toType();
        }
        ksort($types, SORT_NATURAL);
        return array_values($types);
    }

    /**
     * @return list<VarType>
     */
    public function resolveTraits(): array
    {
        $traits = [];
        foreach ($this->file->traits as $comment) {
            $doc = $this->docParser->parse($comment);
            if ($doc->use !== null) {
                $node = $this->resolveFromNode($doc->use->type);
                assert($node instanceof StructureVarType);
                $traits[$node->structure->name] = $node;
            }
        }
        foreach ($this->reflection->getTraits() as $name => $reflection) {
            $traits[$name] ??= $this->instantiateTraitInfo($reflection)->toType();
        }
        return array_values($traits);
    }

    /**
     * @return list<VarType>
     */
    public function resolveDeclaredInterfacesForMethod(string $name): array
    {
        $interfaces = [];
        foreach ($this->reflection->getInterfaces() as $interface) {
            foreach ($interface->getMethods() as $method) {
                if ($method->getName() === $name) {
                    $interfaces[$interface->name] = $this->instantiateInterfaceInfo($interface)->toType();
                    break;
                }
            }
        }
        ksort($interfaces, SORT_NATURAL);
        return array_values($interfaces);
    }

    /**
     * @param ReflectionMethod $reflection
     * @return VarType|null
     */
    public function resolveDeclaringClassForMethod(ReflectionMethod $reflection): ?VarType
    {
        $class = $this->instantiateClassInfo($reflection->getDeclaringClass());

        if ($trait = $this->tryGetDeclaringTrait($reflection, $class)) {
            return $trait->toType();
        }

        if ($reflection->getName() !== $this->reflection->name) {
            return $class->toType();
        }

        return null;
    }

    protected function tryGetDeclaringTrait(ReflectionMethod $reflection, ClassInfo $class): ?TraitInfo
    {
        foreach ($class->traits as $trait) {
            if ($trait instanceof StructureVarType && $trait->structure instanceof TraitInfo) {
                $name = $reflection->name;
                if ($trait->structure->methods[$name] ?? false) {
                    return $trait->structure;
                }
            }
        }
        return null;
    }
    /**
     * @param TypeNode $node
     * @param PhpDoc|null $doc
     * @return VarType
     */
    public function resolveFromNode(TypeNode $node, ?PhpDoc $doc = null): VarType
    {
        if ($node instanceof UnionTypeNode) {
            return new UnionVarType(
                array_map(fn(TypeNode $n) => $this->resolveFromNode($n, $doc), $node->types),
            );
        }

        if ($node instanceof IntersectionTypeNode) {
            return new IntersectionVarType(
                array_map(fn(TypeNode $n) => $this->resolveFromNode($n, $doc), $node->types),
            );
        }

        if ($node instanceof GenericTypeNode) {
            return $this->convertNameToVarType(
                $node->type->name,
                array_values(array_map(fn($n) => $this->resolveFromNode($n, $doc), $node->genericTypes)),
            );
        }

        if ($node instanceof IdentifierTypeNode) {
            return $this->convertNameToVarType($node->name, [], $doc);
        }

        if ($node instanceof ConstTypeNode) {
            if ($node->constExpr instanceof ConstExprIntegerNode) {
                return $this->convertNameToVarType($node->constExpr->value, [], $doc);
            }
            if ($node->constExpr instanceof ConstExprStringNode) {
                return $this->convertNameToVarType($node->constExpr->value, [], $doc);
            }
            // TODO add support for other const types
            throw new UnreachableException();
        }

        if ($node instanceof ArrayShapeNode) {
            // TODO add support for array shapes
            return new NamedVarType((string) $node);
        }

        if ($node instanceof ThisTypeNode) {
            return new NamedVarType('$this');
        }

        if ($node instanceof ConditionalTypeForParameterNode) {
            return new ConditionalVarType(
                $node->parameterName,
                $this->resolveFromNode($node->targetType, $doc),
                $this->resolveFromNode($node->if, $doc),
                $this->resolveFromNode($node->else, $doc),
            );
        }

        if ($node instanceof CallableTypeNode) {
            $name = $node->identifier->name === Closure::class
                ? $this->instantiateClassInfo(new ReflectionClass(Closure::class))->toType()
                : new NamedVarType($node->identifier->name);
            return new CallableVarType(
                $name,
                array_values(array_map(fn($n) => new ParameterVarType(
                    $this->resolveFromNode($n->type, $doc),
                    $n->isReference,
                    $n->isVariadic,
                ), $node->parameters)),
                $this->resolveFromNode($node->returnType, $doc),
            );
        }

        throw new UnreachableException();
    }

    /**
     * @param ReflectionType|null $type
     * @return VarType
     */
    public function resolveFromReflection(?ReflectionType $type): VarType
    {
        if ($type instanceof ReflectionIntersectionType) {
            return new IntersectionVarType(
                array_map(fn($t) => $this->resolveFromReflection($t), $type->getTypes()),
            );
        }

        if ($type instanceof ReflectionUnionType) {
            return new UnionVarType(
                array_map(fn($t) => $this->resolveFromReflection($t), $type->getTypes()),
            );
        }

        if ($type instanceof ReflectionNamedType) {
            return $this->convertNameToVarType($type->getName(), []);
        }

        return new NamedVarType('mixed');
    }

    /**
     * @param string $name
     * @param list<VarType> $generics
     * @param PhpDoc|null $doc
     * @return VarType
     */
    protected function convertNameToVarType(string $name, array $generics, ?PhpDoc $doc = null): VarType
    {
        $fqn = $this->getFullyQualifiedName($name) ?? $name;

        if (class_exists($fqn)) {
            $reflection = new ReflectionClass($fqn);
            $definition = $this->instantiateClassInfo($reflection);
            return new StructureVarType($definition, $generics);
        }

        if (interface_exists($fqn)) {
            $reflection = new ReflectionClass($fqn);
            $definition = $this->instantiateClassInfo($reflection);
            return new StructureVarType($definition, $generics);
        }

        if (enum_exists($fqn)) {
            $reflection = new ReflectionEnum($fqn);
            $definition = $this->instantiateClassInfo($reflection);
            return new StructureVarType($definition, $generics);
        }

        if (trait_exists($fqn)) {
            $reflection = new ReflectionClass($fqn);
            $definition = $this->instantiateTraitInfo($reflection);
            return new StructureVarType($definition, $generics);
        }

        if (
            in_array($fqn, array_map(fn($t) => $t->name, $this->phpDoc->templates), true) ||
            in_array($fqn, array_map(fn($t) => $t->name, $doc->templates ?? []), true)
        ) {
            return new TemplateVarType($fqn);
        }

        return new NamedVarType($fqn, $generics);
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @return ClassInfo
     */
    protected function instantiateClassInfo(ReflectionClass $reflection): ClassInfo
    {
        return new ClassInfo($reflection, $this->docParser, $this->urlResolver, $this);
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @return TraitInfo
     */
    protected function instantiateTraitInfo(ReflectionClass $reflection): TraitInfo
    {
        return new TraitInfo($reflection, $this->docParser, $this->urlResolver, $this);
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @return InterfaceInfo
     */
    protected function instantiateInterfaceInfo(ReflectionClass $reflection): InterfaceInfo
    {
        return new InterfaceInfo($reflection, $this->docParser, $this->urlResolver, $this);
    }

    /**
     * Resolves the fully qualified class name from a given name within the context of a class file.
     *
     * @param string|Stringable $name
     * @return string|null
     */
    public function getFullyQualifiedName(
        string|Stringable $name,
    ): ?string
    {
        if ($name instanceof Stringable) {
            $name = (string) $name;
        }

        // When the type is a fully qualified class name
        if (class_exists($name) || interface_exists($name) || trait_exists($name) || enum_exists($name)) {
            return $name;
        }

        // When the type is imported via use statement
        $class = $this->file->imports[$name] ?? null;
        if (is_string($class)) {
            return $class;
        }

        // When the type is a sibling class in the same namespace
        /** @var class-string $sibling */
        $sibling = $this->reflection->getNamespaceName() . '\\' . $name;
        if (class_exists($sibling) || interface_exists($sibling) || trait_exists($sibling) || enum_exists($sibling)) {
            return $sibling;
        }

        return null;
    }
}
