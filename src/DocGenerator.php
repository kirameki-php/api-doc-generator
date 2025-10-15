<?php

declare(strict_types=1);

namespace Kirameki\ApiDocTools;

use Kirameki\Core\Json;
use Kirameki\Storage\Directory;
use Kirameki\Storage\Storable;
use Kirameki\Text\Str;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
use function array_flip;
use function class_exists;
use function dump;
use function enum_exists;
use function file_get_contents;

class DocGenerator
{
    /**
     * @var array<string, string>
     */
    protected array $pathMap;

    /**
     * @param string $projectRoot
     */
    public function __construct(
        protected string $projectRoot,
    ) {
        $composerContent = file_get_contents($this->projectRoot . '/composer.json') ?: throw new \RuntimeException('Failed to read composer.json');
        $composer = Json::decode($composerContent);
        $this->pathMap = array_flip((array) $composer->autoload->{'psr-4'});
    }

    public function generate(): void
    {
        foreach ($this->pathMap as $path => $namespace) {
            $dir = new Directory("{$this->projectRoot}/{$path}");
            foreach ($dir->scanRecursively() as $storable) {
                $class = $this->getClassIfExists($storable, $path, $namespace);
                dump($class?->getName());
            }
        }
    }

    /**
     * @throws ReflectionException
     */
    protected function getClassIfExists(Storable $storable, string $path, string $namespace): ?ReflectionClass
    {
        $classString = Str::of($storable->pathname)
            ->substringAfter($this->projectRoot . '/' . $path)
            ->substringBeforeLast('.php')
            ->replace('/', '\\')
            ->prepend($namespace)
            ->toString();

       return match(true) {
            class_exists($classString),
            interface_exists($classString),
            trait_exists($classString) => new ReflectionClass($classString),
            enum_exists($classString) => new ReflectionEnum($classString),
            default => null,
       };
    }
}
