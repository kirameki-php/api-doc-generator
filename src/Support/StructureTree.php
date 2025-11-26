<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Support;

use IteratorAggregate;
use Kirameki\ApiDocGenerator\Components\ClassInfo;
use Kirameki\ApiDocGenerator\Components\StructureInfo;
use Traversable;

/**
 * @implements IteratorAggregate<string, StructureInfo>
 */
class StructureTree implements IteratorAggregate
{
    /**
     * @var array<string, StructureTree>
     */
    public array $namespaces = [];

    /**
     * @var array<string, StructureInfo>
     */
    public array $structures = [];

    /**
     * @return void
     */
    public function sortRecursively(): void
    {
        ksort($this->namespaces);
        foreach ($this->namespaces as $namespace) {
            $namespace->sortRecursively();
        }
        ksort($this->structures);
    }

    /**
     * @return Traversable<string, StructureInfo>
     */
    public function getIterator(): Traversable
    {
        foreach ($this->namespaces as $namespace) {
            foreach ($namespace as $name => $class) {
                yield $name => $class;
            }
        }
        foreach ($this->structures as $name => $class) {
            yield $name => $class;
        }
    }
}
