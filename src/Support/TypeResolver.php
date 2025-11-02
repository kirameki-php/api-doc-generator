<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Support;

use Kirameki\ApiDocGenerator\Components\ClassDefinition;
use Kirameki\ApiDocGenerator\Types\IntersectionVarType;
use Kirameki\ApiDocGenerator\Types\NamedVarType;
use Kirameki\ApiDocGenerator\Types\StructureVarType;
use Kirameki\ApiDocGenerator\Types\UnionVarType;
use Kirameki\ApiDocGenerator\Types\VarType;
use Kirameki\Core\Exceptions\UnreachableException;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprIntegerNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
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
use function array_map;
use function array_values;
use function class_exists;
use function enum_exists;
use function interface_exists;

class TypeResolver
{
    public function __construct(
        protected readonly ClassFile $file,
        protected readonly CommentParser $docParser,
        protected readonly UrlResolver $urlResolver,
    ) {
    }

    public function resolveFromNode(TypeNode $node): VarType
    {
        if ($node instanceof UnionTypeNode) {
            return new UnionVarType(array_map($this->resolveFromNode(...), $node->types));
        }

        if ($node instanceof IntersectionTypeNode) {
            return new IntersectionVarType(array_map($this->resolveFromNode(...), $node->types));
        }

        if ($node instanceof GenericTypeNode) {
            return $this->convertNameToVarType(
                $node->type->name,
                array_values(array_map($this->resolveFromNode(...), $node->genericTypes)),
            );
        }

        if ($node instanceof IdentifierTypeNode) {
            return $this->convertNameToVarType($node->name);
        }

        if ($node instanceof ConstTypeNode) {
            if ($node->constExpr instanceof ConstExprIntegerNode) {
                return $this->convertNameToVarType($node->constExpr->value);
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

        throw new UnreachableException();
    }

    /**
     * @param ReflectionType|null $type
     * @return VarType
     */
    public function resolveFromReflection(?ReflectionType $type): VarType
    {
        if ($type instanceof ReflectionIntersectionType) {
            return new IntersectionVarType(array_map($this->resolveFromReflection(...), $type->getTypes()));
        }

        if ($type instanceof ReflectionUnionType) {
            return new UnionVarType(array_map($this->resolveFromReflection(...), $type->getTypes()));
        }

        if ($type instanceof ReflectionNamedType) {
            return $this->convertNameToVarType($type->getName());
        }

        return new NamedVarType('mixed');
    }

    /**
     * @param string $name
     * @param list<VarType> $generics
     * @return VarType
     */
    protected function convertNameToVarType(string $name, array $generics = []): VarType
    {
        $fqn = StructureUtil::getFullyQualifiedName($this->file, $name) ?? $name;

        if (class_exists($fqn)) {
            $reflection = new ReflectionClass($fqn);
            $definition = new ClassDefinition($reflection, $this->file, $this->docParser, $this, $this->urlResolver);
            return new StructureVarType($definition, $generics);
        }

        if (interface_exists($fqn)) {
            $reflection = new ReflectionClass($fqn);
            $definition = new ClassDefinition($reflection, $this->file, $this->docParser, $this, $this->urlResolver);
            return new StructureVarType($definition, $generics);
        }

        if (enum_exists($fqn)) {
            $reflection = new ReflectionEnum($fqn);
            $definition = new ClassDefinition($reflection, $this->file, $this->docParser, $this, $this->urlResolver);
            return new StructureVarType($definition, $generics);
        }

        return new NamedVarType($fqn);
    }
}
