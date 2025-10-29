<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Components;

use Kirameki\ApiDocGenerator\Support\CommentParser;
use Kirameki\ApiDocGenerator\Support\StructureMap;
use Kirameki\ApiDocGenerator\Support\ClassFile;
use Kirameki\Collections\Vec;
use Kirameki\Core\Exceptions\UnreachableException;
use PHPStan\PhpDocParser\Ast\PhpDoc\ExtendsTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ImplementsTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use ReflectionClass;
use Stringable;
use function array_values;
use function assert;
use function class_exists;
use function dump;
use function enum_exists;
use function interface_exists;
use function is_string;
use function ksort;
use function trait_exists;

class ClassDefinition extends ClassInfo
{
    /**
     * @var array<TemplateDefinition>
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
     * @var ExtendInfo|null
     */
    public ?ExtendInfo $parent {
        get => $this->parent ??= $this->resolveParent();
    }

    /**
     * @var list<InterfaceInfo>
     */
    public array $interfaces {
        get => $this->interfaces ??= $this->resolveInterfaces();
    }

    /**
     * @var array<string, ConstantDefinition>
     */
    public array $constants {
        get => $this->constants ??= $this->resolveConstants();
    }

    /**
     * @var array<string, PropertyDefinition>
     */
    public array $properties {
        get => $this->properties ??= $this->resolveProperties();
    }

    /**
     * @var array<string, MethodDefinition>
     */
    public array $methods {
        get => $this->methods ??= $this->resolveMethods();
    }

    protected ClassFile $file {
        get => $this->file ??= new ClassFile($this->reflection);
    }

    /**
     * @var array<class-string<PhpDocTagValueNode>, list<PhpDocTagValueNode>>
     */
    protected array $phpDocTags {
        get => $this->phpDocTags ??= $this->parsePhpDoc();
    }

    /**
     * @param StructureMap $structureMap
     * @param ReflectionClass<object> $reflection
     * @param CommentParser $docParser
     */
    public function __construct(
        protected StructureMap $structureMap,
        protected ReflectionClass $reflection,
        protected CommentParser $docParser,
    )
    {
        parent::__construct($structureMap, $docParser, $reflection);
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @return self
     */
    protected function instantiate(ReflectionClass $reflection): self
    {
        return new self($this->structureMap, $reflection, $this->docParser);
    }

    /**
     * @return array<class-string<PhpDocTagValueNode>, list<PhpDocTagValueNode>>
     */
    protected function parsePhpDoc(): array
    {
        $tags = [];
        if (($comment = $this->reflection->getDocComment()) !== false) {
            foreach ($this->docParser->parse($comment)->children as $doc) {
                if ($doc instanceof PhpDocTagNode) {
                    $tags[$doc->value::class][] = $doc->value;
                }
            }
        }
        return $tags;
    }

    /**
     * @template T of PhpDocTagValueNode
     * @param class-string<T> $type
     * @return Vec<T>
     */
    protected function getDocTagOfType(string $type): Vec
    {
        /** @phpstan-ignore return.type */
        return new Vec($this->phpDocTags[$type] ?? []);
    }

    /**
     * @return array<TemplateDefinition>
     */
    protected function resolveTemplates(): array
    {
        return $this->getDocTagOfType(TemplateTagValueNode::class)
            ->map(fn(TemplateTagValueNode $node) => new TemplateDefinition(
                $node->name,
                $node->bound ? $this->lookUpType($node->bound) : null,
            ))
            ->toArray();
    }

    /**
     * @return ExtendInfo|null
     */
    protected function resolveParent(): ?ExtendInfo
    {
        $reflection = $this->reflection->getParentClass();

        if ($reflection === false) {
            return null;
        }

        $generics = [];
        foreach ($this->getDocTagOfType(ExtendsTagValueNode::class) as $node) {
            foreach ($node->type->genericTypes as $genericType) {
                if ($genericType instanceof IdentifierTypeNode) {
                    $generics[] = new GenericInfo($this->lookUpType($genericType));
                } else {
                    throw new UnreachableException();
                }
            }
        }
        return new ExtendInfo($this->instantiate($reflection), $generics);
    }

    /**
     * @return list<InterfaceInfo>
     */
    protected function resolveInterfaces(): array
    {
        $interfaces = [];
        foreach ($this->getDocTagOfType(ImplementsTagValueNode::class) as $node) {
            $type = $this->lookUpType($node->type->type);
            assert($type instanceof StructureInfo);
            $generics = [];
            foreach ($node->type->genericTypes as $genericType) {
                if ($genericType instanceof IdentifierTypeNode) {
                    $generics[] = new GenericInfo($this->lookUpType($genericType));
                } else {
                    throw new UnreachableException();
                }
            }
            $interfaces[$type->name] = new InterfaceInfo($type, $generics);
        }

        foreach ($this->file->implements as $name) {
            $interfaces[$name] ??= new InterfaceInfo($this->instantiate(new ReflectionClass($name)));
        }
        ksort($interfaces);
        return array_values($interfaces);
    }

    /**
     * @return array<string, ConstantDefinition>
     */
    protected function resolveConstants(): array
    {
        $constants = [];
        foreach ($this->reflection->getReflectionConstants() as $constant) {
            $constants[$constant->name] = new ConstantDefinition($this, $constant);
        }
        return $constants;
    }

    /**
     * @return array<string, PropertyDefinition>
     */
    protected function resolveProperties(): array
    {
        $properties = [];
        foreach ($this->reflection->getProperties() as $property) {
            $properties[$property->name] = new PropertyDefinition(
                $this->structureMap,
                $this->reflection,
                $property,
                $this->docParser,
            );
        }
        return $properties;
    }

    /**
     * @return array<string, MethodDefinition>
     */
    protected function resolveMethods(): array
    {
        $methods = [];
        foreach ($this->reflection->getMethods() as $method) {
            $methods[$method->getName()] = new MethodDefinition(
                $this->structureMap,
                $this->reflection,
                $method,
                $this->docParser,
            );
        }
        return $methods;
    }

    public function lookUpType(string|Stringable $name): StructureInfo|string
    {
        if ($name instanceof Stringable) {
            $name = (string) $name;
        }

        // When the type is a fully qualified class name
        if (class_exists($name) || interface_exists($name) || trait_exists($name) || enum_exists($name)) {
            return new ClassInfo(
                $this->structureMap,
                $this->docParser,
                new ReflectionClass($name),
            );
        }

        // When the type is imported via use statement
        $class = $this->file->imports[$name] ?? null;
        if (is_string($class)) {
            return new ClassInfo(
                $this->structureMap,
                $this->docParser,
                new ReflectionClass($class),
            );
        }

        // When the type is a sibling class in the same namespace
        /** @var class-string $sibling */
        $sibling = $this->namespace . '\\' . $name;
        if ($this->structureMap->exists($sibling)) {
            return new ClassInfo(
                $this->structureMap,
                $this->docParser,
                new ReflectionClass($sibling),
            );
        }

        return $name;
    }
}
