<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Support;

class UrlResolver
{
    /**
     * @param StructureMap $structureMap
     */
    public function __construct(
        protected StructureMap $structureMap,
    ) {
    }
}
