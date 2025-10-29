<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Components;

use Kirameki\ApiDocGenerator\Support\CommentParser;
use Kirameki\ApiDocGenerator\Support\StructureMap;
use Kirameki\Collections\Vec;
use Kirameki\Text\Str;
use ReflectionClass;
use Stringable;

class ClassInfo implements StructureInfo, Stringable
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
     * @var list<GenericInfo>
     */
    public protected(set) array $generics {
        get => $this->generics;
        set => $this->generics = $value;
    }

    /**
     * @param StructureMap $structureMap
     * @param ReflectionClass<object> $reflection
     * @param CommentParser $docParser
     * @param list<GenericInfo> $generics
     */
    public function __construct(
        protected StructureMap $structureMap,
        protected CommentParser $docParser,
        protected ReflectionClass $reflection,
        array $generics = [],
    ) {
        $this->generics = $generics;
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
     * @return string
     */
    public function __toString(): string
    {
        return $this->name;
    }
}
