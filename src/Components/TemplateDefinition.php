<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Components;

use Kirameki\ApiDocGenerator\Types\VarType;

class TemplateDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly VarType|null $bound = null,
        public readonly ?VarType $default = null,
    ) {
    }
}
