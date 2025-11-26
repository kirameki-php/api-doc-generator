<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Support;

use Kirameki\ApiDocGenerator\Components\TraitInfo;

class TraitAliasTarget
{
    public function __construct(
        public readonly TraitInfo $trait,
        public readonly string $method,
    ) {
    }
}
