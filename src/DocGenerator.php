<?php declare(strict_types=1);

namespace Kirameki\ApiDocTools;

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

    /**
     * @param string $basePath
     * @param string $projectRoot
     * @param PageRenderer $renderer
     */
    public function __construct(
        protected string $basePath,
        protected string $projectRoot,
        protected PageRenderer $renderer,
        protected DocParser $docParser,
    ) {
        $composerContent = file_get_contents($this->projectRoot . '/composer.json') ?: throw new \RuntimeException('Failed to read composer.json');
        $composer = Json::decode($composerContent);
        $this->pathMap = array_flip((array) $composer->autoload->{'psr-4'});
    }

    public function generate(): void
    {
        $classes = [];
        foreach ($this->pathMap as $path => $namespace) {
            $dir = new Directory("{$this->projectRoot}/{$path}");
            foreach ($dir->scanRecursively() as $storable) {
                $reflection = $this->getClassIfExists($storable, $path, $namespace);
                if ($reflection !== null) {
                    if ($reflection instanceof ReflectionEnum) {
                        // TODO implement EnumInfo
                    }
                    else if ($reflection->isTrait()) {
//                        $classes[$reflection->name] = new ClassInfo($reflection);
                    }
                    else {
                        $classes[$reflection->name] = new ClassInfo($reflection, $this->docParser);
                    }
                }
            }
        }
        ksort($classes);

        $html = $this->renderer->render(Path::of(__DIR__ . '/views/index.latte'), [
            'basePath' => $this->basePath,
            'references' => $classes,
        ]);

        $sidebarHtml = $this->renderer->render(Path::of(__DIR__ . '/views/sidebar.latte'), [
            'references' => $classes,
        ]);

        @mkdir($this->projectRoot . '/docs', 0755);
        file_put_contents(Path::of(__DIR__ . '/../docs/main.html')->normalize(), $html);

        foreach ($classes as $class) {
            $html = $this->renderer->render(Path::of(__DIR__ . '/views/class.latte'), [
                'basePath' => $this->basePath,
                'sidebarHtml' => $sidebarHtml,
                'class' => $class,
            ]);
            $filePath = Path::of(__DIR__ . '/../docs/' . $class->getHtmlPath())->normalize();
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
}
