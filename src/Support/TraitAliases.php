<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Support;

use Kirameki\ApiDocGenerator\Components\TraitInfo;
use ReflectionClass;
use ReflectionException;

class TraitAliases
{
    /**
     * @var array<string, TraitAliasTarget>
     */
    protected array $aliases = [];

    /**
     * @param ReflectionClass<object> $reflection
     * @throws ReflectionException
     */
    public function __construct(
        ReflectionClass $reflection,
    ) {
        foreach ($reflection->getTraitAliases() as $alias => $method) {
            [$traitName, $method] = explode('::', $method, 2);
            $this->aliases[$alias] = new TraitAliasTarget(
                new TraitInfo(new ReflectionClass($traitName)),
                $method,
            );
        }
    }

    /**
     * @param string $alias
     * @param TraitAliasTarget $target
     * @return void
     */
    public function add(string $alias, TraitAliasTarget $target): void
    {
        $this->aliases[$alias] = $target;
    }

    /**
     * @param string $alias
     * @return bool
     */
    public function exists(string $alias): bool
    {
        return isset($this->aliases[$alias]);
    }
}
