<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Support;

use IteratorAggregate;
use Kirameki\ApiDocGenerator\Components\ClassInfo;
use Traversable;

/**
 * @implements IteratorAggregate<string, ClassInfo>
 */
class Tree implements IteratorAggregate
{
    /**
     * @var array<string, Tree>
     */
    public array $namespaces = [];

    /**
     * @var array<string, ClassInfo>
     */
    public array $classes = [];

    /**
     * @return void
     */
    public function sortRecursively(): void
    {
        ksort($this->namespaces);
        foreach ($this->namespaces as $namespace) {
            $namespace->sortRecursively();
        }
        ksort($this->classes);
    }

    /**
     * @return Traversable<string, ClassInfo>
     */
    public function getIterator(): Traversable
    {
        foreach ($this->namespaces as $namespace) {
            foreach ($namespace as $name => $class) {
                yield $name => $class;
            }
        }
        foreach ($this->classes as $name => $class) {
            yield $name => $class;
        }
    }
}
