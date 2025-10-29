<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Components;

class GenericInfo
{
    public function __construct(
        public readonly string|StructureInfo $type,
        public readonly StructureInfo|string|null $bound = null,
        public ?string $variance = null,
    ) {
    }
}
