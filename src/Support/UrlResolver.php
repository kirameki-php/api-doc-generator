<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Support;

use Kirameki\ApiDocGenerator\Components\StructureDefinition;
use function in_array;
use function strtolower;

class UrlResolver
{
    /**
     * @param StructureMap $structureMap
     */
    public function __construct(
        protected StructureMap $structureMap,
    ) {
    }

    /**
     * @param StructureDefinition $structure
     * @return string|null
     */
    public function resolve(StructureDefinition $structure): ?string
    {
        if ($this->structureMap->exists($structure->name)) {
            return $structure->outputPath;
        }

        if (in_array($structure->namespace, ['', 'Random'], true)) {
            $classPath = str_replace('\\', '-', strtolower($structure->name));
            return "https://www.php.net/manual/class.{$classPath}.php";
        }

        return null;
    }
}
