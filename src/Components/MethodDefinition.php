<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Components;

use Kirameki\ApiDocGenerator\Support\CommentParser;
use Kirameki\ApiDocGenerator\Support\StructureMap;
use Kirameki\Core\Exceptions\UnreachableException;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use function array_map;

class MethodDefinition extends MemberDefinition
{
    /**
     * @var string
     */
    public string $name {
        get => $this->reflection->getName();
    }

    /**
     * @var string
     */
    public string $comment {
        get => $this->comment ??= (string) $this->reflection->getDocComment();
    }

    /**
     * @var TypeInfo
     */
    public TypeInfo $returnType {
        get => $this->returnType ??= $this->convertToTypeInfo($this->reflection->getReturnType());
    }

    /**
     * @var bool
     */
    public bool $isFinal {
        get => $this->reflection->isFinal();
    }

    /**
     * @var bool
     */
    public bool $isAbstract {
        get => $this->reflection->isAbstract();
    }

    /**
     * @var bool
     */
    public bool $isStatic {
        get => $this->reflection->isStatic();
    }

    /**
     * @var Visibility
     */
    public Visibility $visibility {
        get => match (true) {
            $this->reflection->isPublic() => Visibility::Public,
            $this->reflection->isProtected() => Visibility::Protected,
            $this->reflection->isPrivate() => Visibility::Private,
            default => throw new UnreachableException(),
        };
    }

    /**
     * @param StructureMap $structureMap
     * @param ReflectionClass<object> $reflectionClass
     * @param ReflectionMethod $reflection
     * @param CommentParser $docParser
     */
    public function __construct(
        protected StructureMap $structureMap,
        protected ReflectionClass $reflectionClass,
        protected ReflectionMethod $reflection,
        protected CommentParser $docParser,
    ) {
        parent::__construct($reflectionClass, $docParser);
    }

    /**
     * @param ReflectionType|null $type
     * @return TypeInfo
     */
    protected function convertToTypeInfo(?ReflectionType $type): TypeInfo
    {
        if ($type instanceof ReflectionIntersectionType) {
            return new IntersectionTypeInfo(
                array_map($this->convertToTypeInfo(...), $type->getTypes()),
                $type->allowsNull()
            );
        }

        if ($type instanceof ReflectionUnionType) {
            return new UnionTypeInfo(
                array_map($this->convertToTypeInfo(...), $type->getTypes()),
                $type->allowsNull()
            );
        }

        if ($type instanceof ReflectionNamedType) {
            return new NamedTypeInfo($type->getName(), $type->allowsNull());
        }

        return new NamedTypeInfo('mixed', false);
    }
}
