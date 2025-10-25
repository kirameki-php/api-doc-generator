<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator;

use Kirameki\Collections\Utils\Iter;
use Kirameki\Core\Json;
use Kirameki\Storage\Directory;
use Kirameki\Storage\Path;
use Kirameki\Storage\Storable;
use Kirameki\Text\Str;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
use UnitEnum;
use function array_flip;
use function class_exists;
use function dirname;
use function dump;
use function enum_exists;
use function file_get_contents;
use function file_put_contents;
use function mkdir;

class DocGenerator
{
    /**
     * @var array<string, string>
     */
    protected array $pathMap;

    protected StructureMap $structureMap;

    /**
     * @param string $basePath
     * @param string $projectRoot
     * @param PageRenderer $renderer
     * @param CommentParser $docParser
     */
    public function __construct(
        protected string $basePath,
        protected string $projectRoot,
        protected PageRenderer $renderer,
        protected CommentParser $docParser,
    ) {
        $composerContent = file_get_contents($this->projectRoot . '/composer.json') ?: throw new \RuntimeException('Failed to read composer.json');
        $composer = Json::decode($composerContent);
        $this->pathMap = array_flip((array) $composer->autoload->{'psr-4'});
        $this->structureMap = new StructureMap();
    }

    public function generate(): void
    {
        $tree = [];
        foreach ($this->pathMap as $path => $namespace) {
            $dir = new Directory("{$this->projectRoot}/{$path}");
            foreach ($dir->scanRecursively() as $storable) {
                $reflection = $this->getClassIfExists($storable, $path, $namespace);
                if ($reflection !== null) {
                    if ($reflection instanceof ReflectionEnum) {
                        // TODO implement EnumInfo
                    }
                    else if ($reflection->isTrait()) {
                        // TODO implement TraitInfo
                    }
                    else {
                        $classInfo = new ClassInfo($this->structureMap, $reflection, $this->docParser);
                        $this->structureMap->add($classInfo);
                        $this->appendToTree($tree, $classInfo);
                    }
                }
            }
        }
        $this->sortTreeRecursively($tree);

        $docsPath = dirname(__DIR__) . '/docs';
        @mkdir($docsPath, 0755);

        $html = $this->renderer->render(Path::of(__DIR__ . '/views/index.latte'), [
            'structureMap' => $this->structureMap,
            'tree' => $tree,
        ]);
        file_put_contents("{$docsPath}/main.html", $html);

        foreach (Iter::flatten($tree, 100) as $class) {
            $html = $this->renderer->render(Path::of(__DIR__ . '/views/class.latte'), [
                'structureMap' => $this->structureMap,
                'class' => $class,
            ]);
            $filePath = "{$docsPath}/{$class->getHtmlPath()}";
            @mkdir(dirname($filePath), 0755, true);
            file_put_contents($filePath, $html);
        }
    }

    /**
     * @param Storable $storable
     * @param string $path
     * @param string $namespace
     * @return ReflectionClass<object>|ReflectionEnum<UnitEnum>|null
     * @throws ReflectionException
     */
    protected function getClassIfExists(Storable $storable, string $path, string $namespace): ReflectionClass|null
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

    /**
     * @param array<string, mixed> $tree
     * @param ClassInfo $classInfo
     * @return void
     */
    protected function appendToTree(array &$tree, ClassInfo $classInfo): void
    {
        $parts = explode('\\', $classInfo->namespace);
        $current = &$tree;
        foreach ($parts as $part) {
            if (!isset($current[$part])) {
                $current[$part] = [];
            }
            $current = &$current[$part];
        }
        $current[$classInfo->basename] = $classInfo;
    }

    /**
     * @param array<string, mixed> $tree
     * @return void
     */
    protected function sortTreeRecursively(array &$tree): void
    {
        ksort($tree);
        foreach ($tree as &$subtree) {
            if (is_array($subtree)) {
                $this->sortTreeRecursively($subtree);
            }
        }
    }
}
