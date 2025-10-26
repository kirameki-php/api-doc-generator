<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator;

class GenericInfo
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $bound = null,
    ) {

    }
}
