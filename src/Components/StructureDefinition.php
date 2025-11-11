<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Components;

use Kirameki\Collections\Vec;
use Kirameki\Text\Str;

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
     * @var list<TemplateDefinition>
     */
    public array $templates {
        get;
    }

    /**
     * @var string
     */
    public string $outputPath {
        get;
    }

    /**
     * @var string
     */
    public string $url {
        get;
    }
}
