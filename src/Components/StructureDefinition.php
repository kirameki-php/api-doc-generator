<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Components;

interface StructureDefinition
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
     * @return string
     */
    public function getHtmlPath(): string;
}
