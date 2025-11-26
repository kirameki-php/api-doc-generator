<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Components;

use Kirameki\ApiDocGenerator\Support\PhpFile;
use Kirameki\ApiDocGenerator\Support\CommentParser;
use Kirameki\ApiDocGenerator\Support\TypeResolver;
use Kirameki\ApiDocGenerator\Support\UrlResolver;
use Kirameki\ApiDocGenerator\Types\StructureVarType;
use Kirameki\ApiDocGenerator\Types\VarType;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionProperty;
use function array_map;
use function dump;
use function ksort;

class ClassInfo extends StructureInfo
{
    /**
     * @var string
     */
    public string $type {
        get => 'class';
    }

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
        get => $this->templates ??= $this->typeResolver->resolveTemplates();
    }

    /**
     * @var VarType|null
     */
    public ?VarType $parent {
        get => $this->parent ??= $this->typeResolver->resolveParent();
    }

    /**
     * @var list<VarType>
     */
    public array $interfaces {
        get => $this->interfaces ??= $this->typeResolver->resolveInterfaces();
    }

    /**
     * @var list<StructureVarType>
     */
    public array $traits {
        get => $this->traits ??= $this->typeResolver->resolveTraits();
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
     * @var string
     */
    public string $url {
        get => $this->url ??= $this->urlResolver->resolve($this) ?? '';
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @param CommentParser $docParser
     * @param UrlResolver $urlResolver
     * @param TypeResolver $typeResolver
     */
    public function __construct(
        protected readonly ReflectionClass $reflection,
        protected readonly CommentParser $docParser,
        protected readonly UrlResolver $urlResolver,
        protected readonly TypeResolver $typeResolver,
    ) {
    }

    /**
     * @return array<string, MethodInfo>
     */
    protected function resolveMethods(): array
    {
        $methods = [];
        foreach ($this->reflection->getMethods() as $ref) {
            $methods[$ref->name] = new MethodInfo($this, $ref, $this->docParser, $this->typeResolver);
        }
        ksort($methods, SORT_NATURAL);
        return $methods;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * @param list<VarType> $generics
     * @return StructureVarType
     */
    public function toType(array $generics = []): StructureVarType
    {
        return new StructureVarType($this, $generics);
    }
}
