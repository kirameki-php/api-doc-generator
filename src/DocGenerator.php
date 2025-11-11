<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator;

use Kirameki\ApiDocGenerator\Components\ClassDefinition;
use Kirameki\ApiDocGenerator\Support\ClassFile;
use Kirameki\ApiDocGenerator\Support\CommentParserFactory;
use Kirameki\ApiDocGenerator\Support\StructureMap;
use Kirameki\ApiDocGenerator\Support\Tree;
use Kirameki\ApiDocGenerator\Support\TypeResolver;
use Kirameki\ApiDocGenerator\Support\UrlResolver;
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

    protected CommentParserFactory $docParserFactory;

    /**
     * @param string $basePath
     * @param string $projectRoot
     * @param PageRenderer $renderer
     */
    public function __construct(
        protected string $basePath,
        protected string $projectRoot,
        protected PageRenderer $renderer,
    ) {
        $composerContent = file_get_contents($this->projectRoot . '/composer.json') ?: throw new \RuntimeException('Failed to read composer.json');
        $composer = Json::decode($composerContent);
        $this->pathMap = array_flip((array) $composer->autoload->{'psr-4'});
        $this->structureMap = new StructureMap();
        $this->docParserFactory = new CommentParserFactory();
    }

    public function generate(): void
    {
        $tree = new Tree();
        foreach ($this->pathMap as $path => $namespace) {
            $dir = new Directory("{$this->projectRoot}/{$path}");
            foreach ($dir->scanRecursively() as $storable) {
                $reflection = $this->getClassIfExists($storable, $path, $namespace);
                if ($reflection !== null &&
                    (
                        true ||
                        $reflection->getShortName() === 'Map' ||
                        $reflection->getShortName() === 'Enumerator'
                    )
                ) {
                    if ($reflection instanceof ReflectionEnum) {
                        // TODO implement EnumInfo
                    }
                    else if ($reflection->isTrait()) {
                        // TODO implement TraitInfo
                    }
                    else {
                        $file = new ClassFile($reflection);
                        $docParser = $this->docParserFactory->createFor($file);
                        $urlResolver = new UrlResolver($this->structureMap);
                        $classDef = new ClassDefinition($reflection, $file, $docParser, $urlResolver);
                        $this->structureMap->add($classDef);
                        $this->appendToTree($tree, $classDef);
                    }
                }
            }
        }
        $tree->sortRecursively();

        $docsPath = dirname(__DIR__) . '/docs';
        @mkdir($docsPath, 0755);

        $sidebarHtml = $this->renderer->render(Path::of(__DIR__ . '/views/sidebar.latte'), [
            'tree' => $tree,
        ]);

        $html = $this->renderer->render(Path::of(__DIR__ . '/views/index.latte'), [
            'sidebarHtml' => $sidebarHtml,
        ]);
        file_put_contents("{$docsPath}/main.html", $html);

        foreach (Iter::flatten($tree, 100) as $class) {
            /** @var ClassDefinition $class */
            $html = $this->renderer->render(Path::of(__DIR__ . '/views/class.latte'), [
                'sidebarHtml' => $sidebarHtml,
                'structureMap' => $this->structureMap,
                'class' => $class,
            ]);
            $filePath = "{$docsPath}/{$class->outputPath}";
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
            enum_exists($classString) => new ReflectionEnum($classString),
            class_exists($classString),
            interface_exists($classString),
            trait_exists($classString) => new ReflectionClass($classString),
            default => null,
       };
    }

    /**
     * @param Tree $tree
     * @param ClassDefinition $classInfo
     * @return void
     */
    protected function appendToTree(Tree $tree, ClassDefinition $classInfo): void
    {
        $parts = explode('\\', $classInfo->namespace);
        $current = $tree;
        foreach ($parts as $part) {
            $current = $current->namespaces[$part] ??= new Tree();
        }
        $current->classes[$classInfo->basename] = $classInfo;
    }
}
