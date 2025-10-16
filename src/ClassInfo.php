<?php declare(strict_types=1);

namespace Kirameki\ApiDocTools;

use ReflectionClass;
use function array_map;
use function array_values;

class ClassInfo
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
    public bool $isReadOnly {
        get => $this->reflection->isReadOnly();
    }

    /**
     * @var self|null
     */
    public ?self $parent {
        get => $this->parent ??= $this->reflection->getParentClass()
            ? new self($this->reflection->getParentClass())
            : null;
    }

    /**
     * @var list<self>
     */
    public array $interfaces {
        get => $this->interfaces ??= $this->resolveInterfaces();
    }

    /**
     * @var array<string, MethodInfo>
     */
    public array $methods {
        get => $this->methods ??= $this->resolveMethods();
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    public function __construct(
        protected ReflectionClass $reflection,
    ) {
    }

    /**
     * @return list<self>
     */
    public function resolveInterfaces(): array
    {
        return array_values(array_map(
            static fn(ReflectionClass $i) => new self($i),
            $this->reflection->getInterfaces(),
        ));
    }

    /**
     * @return array<string, MethodInfo>
     */
    public function resolveMethods(): array
    {
        $methods = [];
        foreach ($this->reflection->getMethods() as $method) {
            $methods[$method->getName()] = new MethodInfo($this->reflection, $method);
        }
        return $methods;
    }
}
