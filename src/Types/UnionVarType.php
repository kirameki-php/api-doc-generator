<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Types;

class UnionVarType implements VarType
{
    /**
     * @param array<VarType> $types
     */
    public function __construct(
        public readonly array $types,
    ) {
    }
}
