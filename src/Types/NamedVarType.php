<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Types;

class NamedVarType implements VarType
{
    /**
     * @param string $name
     * @param list<VarType> $generics
     */
    public function __construct(
        public readonly string $name,
        public readonly array $generics = [],
    ) {
    }
}
