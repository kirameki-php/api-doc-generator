<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator;

use Kirameki\Collections\Vec;
use Kirameki\Text\Str;
use ReflectionClass;
use function array_map;
use function array_values;

class ClassInfo implements StructureInfo
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
            ? $this->instantiate($this->reflection->getParentClass())
            : null;
    }

    /**
     * @var list<self>
     */
    public array $interfaces {
        get => $this->interfaces ??= $this->resolveInterfaces();
    }

    /**
     * @var array<string, PropertyInfo>
     */
    public array $properties {
        get => $this->properties ??= $this->resolveProperties();
    }

    /**
     * @var array<string, MethodInfo>
     */
    public array $methods {
        get => $this->methods ??= $this->resolveMethods();
    }

    /**
     * @param StructureMap $structureMap
     * @param ReflectionClass<object> $reflection
     * @param CommentParser $docParser
     */
    public function __construct(
        protected StructureMap $structureMap,
        protected ReflectionClass $reflection,
        protected CommentParser $docParser,
    ) {
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @return self
     */
    protected function instantiate(ReflectionClass $reflection): self
    {
        return new self($this->structureMap, $reflection, $this->docParser);
    }

    /**
     * @return string
     */
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
            $this->instantiate(...),
            $this->reflection->getInterfaces(),
        ));
    }

    /**
     * @return array<string, PropertyInfo>
     */
    public function resolveProperties(): array
    {
        $properties = [];
        foreach ($this->reflection->getProperties() as $property) {
            $properties[$property->name] = new PropertyInfo(
                $this->structureMap,
                $this->reflection,
                $property,
                $this->docParser,
            );
        }
        return $properties;
    }

    /**
     * @return array<string, MethodInfo>
     */
    public function resolveMethods(): array
    {
        $methods = [];
        foreach ($this->reflection->getMethods() as $method) {
            $methods[$method->getName()] = new MethodInfo(
                $this->structureMap,
                $this->reflection,
                $method,
                $this->docParser,
            );
        }
        return $methods;
    }
}
