<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Components;

class ExtendInfo
{
    /**
     * @param ClassDefinition $def
     * @param list<GenericInfo|string> $generics
     */
    public function __construct(
        public readonly ClassDefinition $def,
        public readonly array $generics = [],
    ) {
    }
}
