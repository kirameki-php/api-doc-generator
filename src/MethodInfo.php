<?php declare(strict_types=1);

namespace Kirameki\ApiDocTools;

use Kirameki\Core\Exceptions\UnreachableException;
use ReflectionClass;
use ReflectionMethod;

class MethodInfo
{
    /**
     * @var string
     */
    public string $name {
        get => $this->reflection->getName();
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
     * @param ReflectionClass<object> $reflectionClass
     * @param ReflectionMethod $reflection
     */
    public function __construct(
        protected ReflectionClass $reflectionClass,
        protected ReflectionMethod $reflection,
    ) {
    }
}
