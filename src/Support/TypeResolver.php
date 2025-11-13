<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Support;

use Kirameki\ApiDocGenerator\Components\ClassInfo;
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
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Stringable;
use function array_map;
use function array_values;
use function class_exists;
use function dump;
use function enum_exists;
use function in_array;
use function interface_exists;
use function is_string;
use function trait_exists;

class TypeResolver
{
    /**
     * @param PhpDoc $structureDoc
     * @param ClassFile $file
     * @param CommentParser $docParser
     * @param UrlResolver $urlResolver
     */
    public function __construct(
        protected readonly PhpDoc $structureDoc,
        protected readonly ClassFile $file,
        protected readonly CommentParser $docParser,
        protected readonly UrlResolver $urlResolver,
    ) {
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
            return new CallableVarType(
                $node->identifier->name,
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
                array_map(fn($t) => $this->resolveFromReflection($t), $type->getTypes())
            );
        }

        if ($type instanceof ReflectionUnionType) {
            return new UnionVarType(
                array_map(fn($t) => $this->resolveFromReflection($t), $type->getTypes())
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
        $fqn = $this->getFullyQualifiedName($this->file, $name) ?? $name;

        if (class_exists($fqn)) {
            $reflection = new ReflectionClass($fqn);
            $definition = new ClassInfo($reflection, $this->file, $this->docParser, $this->urlResolver, $this);
            return new StructureVarType($definition, $generics);
        }

        if (interface_exists($fqn)) {
            $reflection = new ReflectionClass($fqn);
            $definition = new ClassInfo($reflection, $this->file, $this->docParser, $this->urlResolver, $this);
            return new StructureVarType($definition, $generics);
        }

        if (enum_exists($fqn)) {
            $reflection = new ReflectionEnum($fqn);
            $definition = new ClassInfo($reflection, $this->file, $this->docParser, $this->urlResolver, $this);
            return new StructureVarType($definition, $generics);
        }

        if (
            in_array($fqn, array_map(fn($t) => $t->name, $this->structureDoc->templates), true) ||
            in_array($fqn, array_map(fn($t) => $t->name, $doc->templates ?? []), true)
        ) {
            return new TemplateVarType($fqn);
        }

        return new NamedVarType($fqn, $generics);
    }

    /**
     * Resolves the fully qualified class name from a given name within the context of a class file.
     *
     * @param ClassFile $file
     * @param string|Stringable $name
     * @return string|null
     */
    public function getFullyQualifiedName(
        ClassFile $file,
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
        $class = $file->imports[$name] ?? null;
        if (is_string($class)) {
            return $class;
        }

        // When the type is a sibling class in the same namespace
        /** @var class-string $sibling */
        $sibling = $file->reflection->getNamespaceName() . '\\' . $name;
        if (class_exists($sibling) || interface_exists($sibling) || trait_exists($sibling) || enum_exists($sibling)) {
            return $sibling;
        }

        return null;
    }
}
