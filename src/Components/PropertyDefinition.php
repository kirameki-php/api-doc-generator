<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Components;

use Kirameki\ApiDocGenerator\Support\CommentParser;
use Kirameki\ApiDocGenerator\Support\TypeResolver;
use Kirameki\Core\Exceptions\UnreachableException;
use ReflectionProperty;

class PropertyDefinition extends MemberDefinition
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
     * @param ReflectionProperty $reflection
     * @param CommentParser $docParser
     * @param TypeResolver $typeResolver
     */
    public function __construct(
        protected ReflectionProperty $reflection,
        CommentParser $docParser,
        protected TypeResolver $typeResolver,
    ) {
        parent::__construct($docParser);
    }
}
