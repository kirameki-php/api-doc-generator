<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Components;

interface StructureInfo
{
    /**
     * @var string
     */
    public string $name {
        get;
    }

    /**
     * @var string
     */
    public string $namespace {
        get;
    }

    /**
     * @var string
     */
    public string $basename {
        get;
    }

    /**
     * @var list<TypeInfo|string>
     */
    public protected(set) array $generics {
        get;
        set;
    }

    /**
     * @return string
     */
    public function getHtmlPath(): string;
}
