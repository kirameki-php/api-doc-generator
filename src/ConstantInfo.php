<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator;

use Kirameki\Core\Exceptions\UnreachableException;
use Kirameki\Text\Str;
use ReflectionClassConstant;

class ConstantInfo
{
    /**
     * @var string
     */
    public string $name {
        get => $this->reflection->name;
    }

    /**
     * @var string
     */
    public string $namespace {
        get => $this->class->namespace;
    }

    /**
     * @var string
     */
    public string $basename {
        get => Str::substringAfterLast($this->name, '\\');
    }

    /**
     * @var mixed
     */
    public mixed $value {
        get => $this->reflection->getValue();
    }

    /**
     * @var bool
     */
    public bool $isFinal {
        get => $this->reflection->isFinal();
    }

    /**
     * @var string|null
     */
    public ?string $type {
        get => $this->reflection->getType()?->getName() ?? null;
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
     * @param ClassInfo $class
     * @param ReflectionClassConstant $reflection
     */
    public function __construct(
        protected ClassInfo $class,
        protected ReflectionClassConstant $reflection,
    )
    {
    }
}
