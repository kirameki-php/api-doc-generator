<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Types;

class ConstVarType implements VarType
{
    /**
     * @param string $value
     */
    public function __construct(
        public readonly string $value,
    ) {
    }
}
