<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Types;

class ParameterVarType implements VarType
{
    /**
     * @param VarType $type
     * @param bool $byReference
     * @param bool $isVariadic
     */
    public function __construct(
        public readonly VarType $type,
        public readonly bool $byReference = false,
        public readonly bool $isVariadic = false,
    ) {
    }
}
