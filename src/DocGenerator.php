<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator;

use Kirameki\ApiDocGenerator\Components\ClassInfo;
use Kirameki\ApiDocGenerator\Components\InterfaceInfo;
use Kirameki\ApiDocGenerator\Components\StructureInfo;
use Kirameki\ApiDocGenerator\Components\TraitInfo;
use Kirameki\ApiDocGenerator\Support\CommentParser;
use Kirameki\ApiDocGenerator\Support\PhpFile;
use Kirameki\ApiDocGenerator\Support\CommentParserFactory;
use Kirameki\ApiDocGenerator\Support\StructureMap;
use Kirameki\ApiDocGenerator\Support\StructureTree;
use Kirameki\ApiDocGenerator\Support\TraitAliases;
use Kirameki\ApiDocGenerator\Support\TypeResolver;
use Kirameki\ApiDocGenerator\Support\UrlResolver;
use Kirameki\Collections\Utils\Iter;
use Kirameki\Core\Exceptions\UnreachableException;
use Kirameki\Core\Json;
use Kirameki\Storage\Directory;
use Kirameki\Storage\Path;
use Kirameki\Storage\Storable;
use Kirameki\Text\Str;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
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

    /**
     * @var StructureMap
     */
    protected StructureMap $structureMap;

    /**
     * @var StructureTree
     */
    protected StructureTree $structureTree;

    /**
     * @var CommentParser
     */
    protected CommentParser $docParser;

    /**
     * @var UrlResolver
     */
    protected UrlResolver $urlResolver;

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
        $this->structureTree = new StructureTree();
        $this->docParser = CommentParserFactory::create();
        $this->urlResolver = new UrlResolver($this->structureMap);
    }

    public function generate(): void
    {
        foreach ($this->pathMap as $path => $namespace) {
            $dir = new Directory("{$this->projectRoot}/{$path}");
            foreach ($dir->scanRecursively() as $storable) {
                $reflection = $this->getClassIfExists($storable, $path, $namespace);
                if ($reflection !== null &&
                    (
                        true ||
                        $reflection->getShortName() === 'VecMutable' ||
                        $reflection->getShortName() === 'Enumerable' ||
                        $reflection->getShortName() === 'Enumerator'
                    )
                ) {
                    $info = $this->generateStructureInfo($reflection);
                    $this->structureMap->add($info);
                    $this->appendToTree($info);
                }
            }
        }
        $this->structureTree->sortRecursively();

        $docsPath = dirname(__DIR__) . '/docs';
        @mkdir($docsPath, 0755);

        $sidebarHtml = $this->renderer->render(Path::of(__DIR__ . '/views/sidebar.latte'), [
            'tree' => $this->structureTree,
        ]);

        $html = $this->renderer->render(Path::of(__DIR__ . '/views/index.latte'), [
            'sidebarHtml' => $sidebarHtml,
        ]);
        file_put_contents("{$docsPath}/main.html", $html);

        foreach (Iter::flatten($this->structureTree, 100) as $info) {
            $path = match(true) {
                $info instanceof ClassInfo => Path::of(__DIR__ . '/views/class.latte'),
                default => throw new UnreachableException(),
            };
            $html = $this->renderer->render($path, ['sidebarHtml' => $sidebarHtml, 'info' => $info]);
            $filePath = "{$docsPath}/{$info->outputPath}";
            @mkdir(dirname($filePath), 0755, true);
            file_put_contents($filePath, $html);
        }
    }

    /**
     * @param Storable $storable
     * @param string $path
     * @param string $namespace
     * @return ReflectionClass<object>|null
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
     * @param ReflectionClass<object> $reflection
     * @return StructureInfo
     */
    protected function generateStructureInfo(ReflectionClass $reflection): StructureInfo
    {
        $typeResolver = $this->createTypeResolverFor($reflection);

        if ($reflection instanceof ReflectionEnum) {
            // TODO implement EnumInfo
        }

        if ($reflection->isTrait()) {
            return new TraitInfo($reflection, $this->docParser, $this->urlResolver, $typeResolver);
        }

        if ($reflection->isInterface()) {
            return new InterfaceInfo($reflection, $this->docParser, $this->urlResolver, $typeResolver);
        }

        return new ClassInfo($reflection, $this->docParser, $this->urlResolver, $typeResolver);
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @return TypeResolver
     */
    protected function createTypeResolverFor(ReflectionClass $reflection): TypeResolver
    {
        return new TypeResolver(
            $reflection,
            new PhpFile($reflection),
            $this->docParser,
            $this->urlResolver,
            new TraitAliases($reflection),
            $this->structureMap,
        );
    }

    /**
     * @param StructureInfo $info
     * @return void
     */
    protected function appendToTree(StructureInfo $info): void
    {
        $parts = explode('\\', $info->namespace);
        $current = $this->structureTree;
        foreach ($parts as $part) {
            $current = $current->namespaces[$part] ??= new StructureTree();
        }
        $current->structures[$info->basename] = $info;
    }
}
