<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Components;

class TemplateDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly StructureInfo|string|null $bound = null,
    ) {
    }
}
