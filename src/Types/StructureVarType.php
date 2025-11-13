<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Types;

use Kirameki\ApiDocGenerator\Components\StructureInfo;

class StructureVarType implements VarType
{
    /**
     * @param StructureInfo $structure
     * @param list<VarType> $generics
     */
    public function __construct(
        public readonly StructureInfo $structure,
        public readonly array $generics = [],
    ) {
    }
}
