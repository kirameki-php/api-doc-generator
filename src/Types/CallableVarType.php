<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Types;

class CallableVarType implements VarType
{
    /**
     * @param VarType $name
     * @param list<ParameterVarType> $parameters
     * @param VarType $returnType
     */
    public function __construct(
        public readonly VarType $name,
        public readonly array $parameters,
        public readonly VarType $returnType,
    ) {
    }
}
