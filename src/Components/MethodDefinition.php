<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Components;

use Kirameki\ApiDocGenerator\Support\CommentParser;
use Kirameki\ApiDocGenerator\Support\StructureMap;
use Kirameki\Core\Exceptions\UnreachableException;
use ReflectionClass;
use ReflectionMethod;

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
}
