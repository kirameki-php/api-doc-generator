<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Types;

class CallableVarType implements VarType
{
    /**
     * @param string $name
     * @param list<ParameterVarType> $parameters
     * @param VarType $returnType
     */
    public function __construct(
        public readonly string $name,
        public readonly array $parameters,
        public readonly VarType $returnType,
    ) {
    }
}
