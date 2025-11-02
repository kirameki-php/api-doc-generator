<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Support;

use Kirameki\ApiDocGenerator\Components\StructureDefinition;
use Stringable;

class StructureMap
{
    /**
     * @var array<string, StructureDefinition>
     */
    protected array $map = [];

    /**
     * @param StructureDefinition $info
     * @return void
     */
    public function add(StructureDefinition $info): void
    {
        $this->map[$info->name] = $info;
    }

    /**
     * @param string|Stringable $name
     * @return bool
     */
    public function exists(string|Stringable $name): bool
    {
        return isset($this->map[(string) $name]);
    }
}
