<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Support;

use ReflectionClass;

class TraitAliases
{
    /**
     * @var array<string, class-string>
     */
    protected array $aliases = [];

    /**
     * @var array<class-string, array<string, string>>
     */
    protected array $flippedAliases = [];

    /**
     * @param ReflectionClass<object> $reflection
     */
    public function __construct(
        ReflectionClass $reflection,
    ) {
        foreach ($reflection->getTraitAliases() as $alias => $method) {
            /** @var class-string $traitName */
            [$traitName, $method] = explode('::', $method, 2);
            $this->aliases[$alias] = $traitName;
            $this->flippedAliases[$traitName][$method] = $alias;
        }
    }

    /**
     * @param string $method
     * @return class-string|null
     */
    public function getDeclaringTraitFor(string $method): ?string
    {
        return $this->aliases[$method] ?? null;
    }

    /**
     * @param string $trait
     * @param string $method
     * @return bool
     */
    public function isAliased(string $trait, string $method): bool
    {
        return isset($this->flippedAliases[$trait][$method]);
    }
}
