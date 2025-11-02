<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Types;

use Kirameki\ApiDocGenerator\Components\StructureDefinition;

class StructureVarType implements VarType
{
    /**
     * @param StructureDefinition $structure
     * @param list<VarType> $generics
     */
    public function __construct(
        public readonly StructureDefinition $structure,
        public readonly array $generics = [],
    ) {
    }
}
