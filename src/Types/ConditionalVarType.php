<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Types;

class ConditionalVarType implements VarType
{
    /**
     * @param string $variable
     * @param VarType $matchType
     * @param VarType $trueType
     * @param VarType $falseType
     */
    public function __construct(
        public readonly string $variable,
        public readonly VarType $matchType,
        public readonly VarType $trueType,
        public readonly VarType $falseType,
    ) {
    }
}
