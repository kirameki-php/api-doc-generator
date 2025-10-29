<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Components;

class NamedTypeInfo implements TypeInfo
{
    public function __construct(
        public readonly string|StructureInfo $type,
        public readonly bool $nullable = false,
        public readonly ?string $variance = null,
    ) {
    }
}
