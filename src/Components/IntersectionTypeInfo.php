<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Components;

class IntersectionTypeInfo implements TypeInfo
{
    /**
     * @param array<TypeInfo> $types
     */
    public function __construct(
        public readonly array $types,
        public readonly bool $nullable,
        public readonly ?string $variance = null,
    ) {
    }
}
