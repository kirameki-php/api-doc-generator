<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Components;

class InterfaceInfo
{
    /**
     * @param StructureInfo $def
     * @param list<GenericInfo|string> $generics
     */
    public function __construct(
        public readonly StructureInfo $def,
        public readonly array $generics = [],
    ) {
    }
}
