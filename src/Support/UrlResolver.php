<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Support;

class UrlResolver
{
    public function __construct(
        protected StructureMap $structureMap,
    ) {
    }
}
