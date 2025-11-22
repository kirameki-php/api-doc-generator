<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Components;

use Kirameki\ApiDocGenerator\Support\PhpDoc;
use Kirameki\ApiDocGenerator\Support\PhpFile;
use Kirameki\ApiDocGenerator\Support\CommentParser;
use Kirameki\ApiDocGenerator\Support\TypeResolver;
use Kirameki\ApiDocGenerator\Support\UrlResolver;
use Kirameki\ApiDocGenerator\Types\StructureVarType;
use Kirameki\ApiDocGenerator\Types\VarType;
use Kirameki\Collections\Vec;
use Kirameki\Text\Str;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionProperty;
use function array_map;
use function array_values;
use function assert;
use function ksort;

class ClassInfo implements StructureInfo
{
    /**
     * @var string
     */
    public string $type = 'class';

    /**
     * @var string
     */
    public string $name {
        get => $this->reflection->getName();
    }

    /**
     * @var string
     */
    public string $namespace {
        get => $this->reflection->getNamespaceName();
    }

    /**
     * @var string
     */
    public string $basename {
        get => Str::substringAfterLast($this->name, '\\');
    }

    /**
     * @var bool
     */
    public bool $isFinal {
        get => $this->reflection->isFinal();
    }

    /**
     * @var bool
     */
    public bool $isAbstract {
        get => $this->reflection->isAbstract();
    }

    /**
     * @var bool
     */
    public bool $isReadOnly {
        get => $this->reflection->isReadOnly();
    }

    /**
     * @var list<TemplateInfo>
     */
    public array $templates {
        get => $this->templates ??= $this->resolveTemplates();
    }

    /**
     * @var VarType|null
     */
    public ?VarType $parent {
        get => $this->parent ??= $this->resolveParent();
    }

    /**
     * @var list<VarType>
     */
    public array $interfaces {
        get => $this->interfaces ??= $this->resolveInterfaces();
    }

    /**
     * @var list<VarType>
     */
    public array $traits {
        get => $this->traits ??= $this->resolveTraits();
    }

    /**
     * @var list<ConstantInfo>
     */
    public array $constants {
        get => $this->constants ??= array_map(
            fn(ReflectionClassConstant $ref) => new ConstantInfo($this, $ref),
            $this->reflection->getReflectionConstants(),
        );
    }

    /**
     * @var list<PropertyInfo>
     */
    public array $properties {
        get => $this->properties ??= array_map(
            fn(ReflectionProperty $ref) => new PropertyInfo($ref, $this->docParser, $this->typeResolver),
            $this->reflection->getProperties(),
        );
    }

    /**
     * @var array<string, MethodInfo>
     */
    public array $methods {
        get => $this->methods ??= $this->resolveMethods();
    }

    /**
     * @var PhpDoc
     */
    protected PhpDoc $phpDoc {
        get => $this->phpDoc ??= $this->docParser->parse((string) $this->reflection->getDocComment());
    }

    /**
     * @var TypeResolver
     */
    protected TypeResolver $typeResolver;

    /**
     * @var string
     */
    public string $outputPath {
        get => $this->outputPath ??= new Vec(Str::split($this->name, '\\'))
                ->map(Str::toKebabCase(...))
                ->prepend('classes')
                ->join('/') . '.html';
    }

    /**
     * @var string
     */
    public string $url {
        get => $this->url ??= $this->urlResolver->resolve($this) ?? '';
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @param PhpFile $file
     * @param CommentParser $docParser
     * @param UrlResolver $urlResolver
     * @param TypeResolver|null $typeResolver
     */
    public function __construct(
        public readonly ReflectionClass $reflection,
        protected readonly PhpFile $file,
        protected readonly CommentParser $docParser,
        protected readonly UrlResolver $urlResolver,
        ?TypeResolver $typeResolver = null,
    ) {
        $this->typeResolver = $typeResolver ?? new TypeResolver(
            $this->phpDoc,
            $this->file,
            $this->docParser,
            $this->urlResolver,
        );
    }

    /**
     * @return list<TemplateInfo>
     */
    protected function resolveTemplates(): array
    {
        $templates = [];
        foreach ($this->phpDoc->templates as $tag) {
            $templates[] = new TemplateInfo(
                $tag->name,
                $tag->bound
                    ? $this->getTypeFromNode($tag->bound)
                    : null,
                $tag->default
                    ? $this->getTypeFromNode($tag->default)
                    : null,
            );
        }
        return $templates;
    }

    /**
     * @return list<VarType>
     */
    protected function resolveTraits(): array
    {
        $traits = [];
        foreach ($this->file->traits as $comment) {
            $doc = $this->docParser->parse($comment);
            if ($doc->use !== null) {
                $node = $this->getTypeFromNode($doc->use->type);
                assert($node instanceof StructureVarType);
                $traits[$node->structure->name] = $node;
            }
        }
        foreach ($this->reflection->getTraits() as $name => $reflection) {
            $traits[$name] ??= new StructureVarType(
                new TraitInfo($reflection, $this->file, $this->docParser, $this->urlResolver, $this->typeResolver),
            );
        }
        return array_values($traits);
    }

    /**
     * @return VarType|null
     */
    protected function resolveParent(): ?VarType
    {
        $node = $this->phpDoc->extends?->type;
        if ($node !== null) {
            return $this->getTypeFromNode($node);
        }
        $reflection = $this->reflection->getParentClass();
        if ($reflection === false) {
            return null;
        }
        return new StructureVarType($this->instantiateClass($reflection));
    }

    /**
     * @return list<VarType>
     */
    protected function resolveInterfaces(): array
    {
        $types = [];
        foreach ($this->phpDoc->implements as $tag) {
            $types[$tag->type->type->name] = $this->getTypeFromNode($tag->type);
        }
        foreach ($this->file->implements as $if) {
            $reflection = new ReflectionClass($if);
            $types[$reflection->getName()] ??= new StructureVarType($this->instantiateClass($reflection));
        }
        ksort($types, SORT_NATURAL);
        return array_values($types);
    }

    /**
     * @return array<string, MethodInfo>
     */
    protected function resolveMethods(): array
    {
        $methods = [];
        foreach ($this->reflection->getMethods() as $ref) {
            $methods[$ref->getName()] = new MethodInfo($this, $ref, $this->docParser, $this->typeResolver);
        }
        ksort($methods, SORT_NATURAL);
        return $methods;
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @return ClassInfo
     */
    public function instantiateClass(ReflectionClass $reflection): ClassInfo
    {
        return new ClassInfo(
            $reflection,
            $this->file,
            $this->docParser,
            $this->urlResolver,
            $this->typeResolver,
        );
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @return InterfaceInfo
     */
    public function instantiateInterface(ReflectionClass $reflection): InterfaceInfo
    {
        return new InterfaceInfo(
            $reflection,
            $this->file,
            $this->docParser,
            $this->urlResolver,
            $this->typeResolver,
        );
    }

    /**
     * @param TypeNode $node
     * @return VarType
     */
    protected function getTypeFromNode(TypeNode $node): VarType
    {
        return $this->typeResolver->resolveFromNode($node);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->name;
    }
}
