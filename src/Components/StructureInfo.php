<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Components;

use Kirameki\Collections\Vec;
use Kirameki\Text\Str;
use Stringable;

abstract class StructureInfo implements Stringable
{
    /**
     * @var string
     */
    public abstract string $type {
        get;
    }

    /**
     * @var string
     */
    public abstract string $name {
        get;
    }

    /**
     * @var string
     */
    public abstract string $namespace {
        get;
    }

    /**
     * @var string
     */
    public string $basename {
        get => Str::substringAfterLast($this->name, '\\');
    }

    /**
     * @var list<TemplateInfo>
     */
    public abstract array $templates {
        get;
    }

    /**
     * @var string
     */
    public string $outputPath {
        get => $this->outputPath ??= new Vec(Str::split($this->name, '\\'))
            ->map(Str::toKebabCase(...))
            ->prepend('classes')
            ->join('/') . '.html';
    }

    /**
     * @var string
     */
    public abstract string $url {
        get;
    }

    /**
     * @var bool
     */
    public abstract bool $isAbstract {
        get;
    }
}
