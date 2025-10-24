<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator;

use Kirameki\Core\Exceptions\UnreachableException;
use ReflectionClass;
use ReflectionProperty;

class PropertyInfo extends MemberInfo
{
    /**
     * @var string
     */
    public string $name {
        get => $this->reflection->getName();
    }

    /**
     * @var string|null
     */
    public ?string $type {
        get => $this->type ??= $this->reflection->hasType()
            ? (string) $this->reflection->getType()
            : null;
    }

    /**
     * @var string
     */
    public string $comment {
        get => $this->comment ??= (string) $this->reflection->getDocComment();
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
     * @var bool
     */
    public bool $isReadOnly {
        get => $this->reflection->isReadOnly();
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
     * @param ReflectionProperty $reflection
     * @param CommentParser $docParser
     */
    public function __construct(
        protected StructureMap $structureMap,
        protected ReflectionClass $reflectionClass,
        protected ReflectionProperty $reflection,
        protected CommentParser $docParser,
    ) {
        parent::__construct($reflectionClass, $docParser);
    }
}
