<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator;

use IteratorAggregate;
use Traversable;

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
