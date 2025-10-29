<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Support;

use Kirameki\ApiDocGenerator\Components\StructureInfo;

class StructureMap
{
    /**
     * @var array<string, StructureInfo>
     */
    protected array $map = [];

    /**
     * @param StructureInfo $info
     * @return void
     */
    public function add(StructureInfo $info): void
    {
        $this->map[$info->name] = $info;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function exists(string $name): bool
    {
        return isset($this->map[$name]);
    }
}
