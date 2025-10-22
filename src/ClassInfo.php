<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator;

use Kirameki\Collections\Vec;
use Kirameki\Text\Str;
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
     * @var string
     */
    public string $namespace {
        get => $this->reflection->getNamespaceName();
    }

    /**
     * @var string
     */
    public string $basename {
        get => Str::substringAfterLast($this->name, '\\');
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
            ? new self($this->reflection->getParentClass(), $this->docParser)
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
     * @param DocParser $docParser
     */
    public function __construct(
        protected ReflectionClass $reflection,
        protected DocParser $docParser,
    ) {
    }

    public function getHtmlPath(): string
    {
        return new Vec(Str::split($this->name, '\\'))
            ->map(Str::toKebabCase(...))
            ->prepend('classes')
            ->join('/') . '.html';
    }

    /**
     * @return list<self>
     */
    public function resolveInterfaces(): array
    {
        return array_values(array_map(
            fn(ReflectionClass $i) => new self($i, $this->docParser),
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
            $methods[$method->getName()] = new MethodInfo($this->reflection, $method, $this->docParser);
        }
        return $methods;
    }
}
