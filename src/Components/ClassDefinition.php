<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Components;

use Kirameki\ApiDocGenerator\Support\ClassFile;
use Kirameki\ApiDocGenerator\Support\CommentParser;
use Kirameki\ApiDocGenerator\Support\PhpDoc;
use Kirameki\ApiDocGenerator\Support\TypeResolver;
use Kirameki\ApiDocGenerator\Support\UrlResolver;
use Kirameki\ApiDocGenerator\Types\StructureVarType;
use Kirameki\ApiDocGenerator\Types\VarType;
use Kirameki\Collections\Vec;
use Kirameki\Text\Str;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;
use ReflectionProperty;
use Stringable;
use function array_map;
use function array_values;
use function dump;
use function ksort;

class ClassDefinition implements StructureDefinition, Stringable
{
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
     * @var list<TemplateDefinition>
     */
    public array $templates {
        get => $this->templates ??= $this->resolveTemplates();
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
     * @var list<ConstantDefinition>
     */
    public array $constants {
        get => $this->constants ??= array_map(
            fn(ReflectionClassConstant $ref) => new ConstantDefinition($this, $ref),
            $this->reflection->getReflectionConstants(),
        );
    }

    /**
     * @var list<PropertyDefinition>
     */
    public array $properties {
        get => $this->properties ??= array_map(
            fn(ReflectionProperty $ref) => new PropertyDefinition($ref, $this->docParser, $this->typeResolver),
            $this->reflection->getProperties(),
        );
    }

    /**
     * @var list<MethodDefinition>
     */
    public array $methods {
        get => $this->methods ??= array_map(
            fn(ReflectionMethod $ref) => new MethodDefinition($this, $ref, $this->docParser, $this->typeResolver),
            $this->reflection->getMethods(),
        );
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
    protected TypeResolver $typeResolver {
        get => $this->typeResolver;
    }

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
     * @param ReflectionClass<object> $reflection
     * @param ClassFile $file
     * @param CommentParser $docParser
     * @param UrlResolver $urlResolver
     * @param TypeResolver|null $typeResolver
     */
    public function __construct(
        protected readonly ReflectionClass $reflection,
        protected readonly ClassFile $file,
        protected readonly CommentParser $docParser,
        protected readonly UrlResolver $urlResolver,
        ?TypeResolver $typeResolver = null,
    ) {
        $this->typeResolver = $typeResolver ?? new TypeResolver($this->phpDoc, $this->file, $this->docParser, $this->urlResolver);
    }

    /**
     * @return list<TemplateDefinition>
     */
    protected function resolveTemplates(): array
    {
        $templates = [];
        foreach ($this->phpDoc->templates as $tag) {
            $templates[] = new TemplateDefinition(
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
        return new StructureVarType($this->instantiate($reflection));
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
            $types[$reflection->getName()] ??= new StructureVarType($this->instantiate($reflection));
        }

        ksort($types, SORT_NATURAL);

        return array_values($types);
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
     * @param ReflectionClass<object> $reflection
     * @return ClassDefinition
     */
    protected function instantiate(ReflectionClass $reflection): ClassDefinition
    {
        return new ClassDefinition(
            $reflection,
            $this->file,
            $this->docParser,
            $this->urlResolver,
            $this->typeResolver,
        );
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->name;
    }
}
