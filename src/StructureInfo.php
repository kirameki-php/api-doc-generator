<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator;

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
}
