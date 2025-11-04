<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Types;

class TemplateVarType implements VarType
{
    /**
     * @param string $name
     */
    public function __construct(
        public readonly string $name,
    ) {
    }
}
