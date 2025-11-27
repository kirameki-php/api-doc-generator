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
use function dump;
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
     * @param ReflectionClass<covariant object> $reflection
     * @param PhpFile $file
     * @param CommentParser $docParser
     * @param UrlResolver $urlResolver
     * @param TraitAliases $traitAliases
     * @param StructureMap $structureMap
     */
    public function __construct(
        protected readonly ReflectionClass $reflection,
        protected readonly PhpFile $file,
        protected readonly CommentParser $docParser,
        protected readonly UrlResolver $urlResolver,
        protected readonly TraitAliases $traitAliases,
        protected readonly StructureMap $structureMap,
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
        $parent = $this->structureMap->get($reflection->name) ?? $this->getClassInfo($reflection);
        return $parent->toType();
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
            $types[$reflection->name] ??= $this->getClassInfo($reflection)->toType();
        }
        ksort($types, SORT_NATURAL);
        return array_values($types);
    }

    /**
     * @return list<StructureVarType>
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
            $traits[$name] ??= $this->getTraitInfo($reflection)->toType();
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
                    $interfaces[$interface->name] = $this->getInterfaceInfo($interface)->toType();
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
        $class = $this->getClassInfo($reflection->getDeclaringClass());

        if ($trait = $this->tryGetDeclaringTrait($reflection, $class)) {
            return $trait->toType();
        }

        if ($class->name !== $this->reflection->name) {
            return $class->toType();
        }

        return null;
    }

    protected function tryGetDeclaringTrait(ReflectionMethod $reflection, ClassInfo $class): ?TraitInfo
    {
        $method = $reflection->name;

        if ($traitName = $this->traitAliases->getDeclaringTraitFor($method)) {
            return $this->getTraitInfo(new ReflectionClass($traitName));
        }

        foreach ($class->traits as $type) {
            if ($type->structure instanceof TraitInfo) {
                $trait = $type->structure;
                if (isset($trait->methods[$method]) && !$this->traitAliases->isAliased($trait->name, $method)) {
                    return $trait;
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
                ? $this->getClassInfo(new ReflectionClass(Closure::class))->toType()
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
            return $this->getClassInfo(new ReflectionClass($fqn))->toType($generics);
        }

        if (interface_exists($fqn)) {
            return $this->getClassInfo(new ReflectionClass($fqn))->toType($generics);
        }

        if (enum_exists($fqn)) {
            return $this->getClassInfo(new ReflectionEnum($fqn))->toType($generics);
        }

        if (trait_exists($fqn)) {
            return $this->getTraitInfo(new ReflectionClass($fqn))->toType($generics);
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
     * @param ReflectionClass<covariant object> $reflection
     * @return ClassInfo
     */
    protected function getClassInfo(ReflectionClass $reflection): ClassInfo
    {
        $classFromMap = $this->structureMap->get($reflection->name);
        if ($classFromMap instanceof ClassInfo) {
            return $classFromMap;
        }
        return new ClassInfo($reflection, $this->docParser, $this->urlResolver, $this);
    }

    /**
     * @param ReflectionClass<covariant object> $reflection
     * @return TraitInfo
     */
    protected function getTraitInfo(ReflectionClass $reflection): TraitInfo
    {
        $traitForMap = $this->structureMap->get($reflection->name);
        if ($traitForMap instanceof TraitInfo) {
            return $traitForMap;
        }
        return new TraitInfo($reflection, $this->docParser, $this->urlResolver, $this);
    }

    /**
     * @param ReflectionClass<covariant object> $reflection
     * @return InterfaceInfo
     */
    protected function getInterfaceInfo(ReflectionClass $reflection): InterfaceInfo
    {
        $interfaceForMap = $this->structureMap->get($reflection->name);
        if ($interfaceForMap instanceof InterfaceInfo) {
            return $interfaceForMap;
        }
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
