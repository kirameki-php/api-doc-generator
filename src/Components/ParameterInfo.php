<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Components;

use Kirameki\ApiDocGenerator\Support\TypeResolver;
use Kirameki\ApiDocGenerator\Types\VarType;
use ReflectionParameter;

class ParameterInfo
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
    public string $description {
        get => $this->description ??= $this->resolveDescription();
    }

    /**
     * @var VarType|null
     */
    public ?VarType $type {
        get => $this->type ??= $this->resolveType();
    }

    /**
     * @var VarType|null
     */
    public ?VarType $docType {
        get => $this->docType ??= $this->resolveDocType();
    }

    /**
     * @var bool
     */
    public bool $hasDefault {
        get => $this->reflection->isDefaultValueAvailable();
    }

    /**
     * @var string|null
     */
    public mixed $defaultValue {
        get => $this->reflection->getDefaultValue();
    }

    /**
     * @var string|null
     */
    public ?string $defaultConstant {
        get => $this->defaultConstant ??= $this->reflection->getDefaultValueConstantName();
    }

    /**
     * @var bool
     */
    public bool $isPassedByReference {
        get => $this->reflection->isPassedByReference();
    }

    /**
     * @var bool
     */
    public bool $isVariadic {
        get => $this->reflection->isVariadic();
    }

    /**
     * @param ClassInfo $class
     * @param MethodInfo $method
     * @param ReflectionParameter $reflection
     * @param TypeResolver $typeResolver
     * @param PropertyInfo|null $promotedProperty
     */
    public function __construct(
        protected readonly ClassInfo $class,
        protected readonly MethodInfo $method,
        protected readonly ReflectionParameter $reflection,
        protected readonly TypeResolver $typeResolver,
        protected readonly ?PropertyInfo $promotedProperty = null,
    ) {
    }

    /**
     * @return string
     */
    protected function resolveDescription(): string
    {
        return $this->method->phpDoc->params['$' . $this->name]->description ?? '';
    }

    /**
     * @return VarType|null
     */
    protected function resolveDocType(): ?VarType
    {
        $node = $this->method->phpDoc->params['$' . $this->name] ?? null;
        return $node !== null
            ? $this->typeResolver->resolveFromNode($node->type, $this->method->phpDoc)
            : $this->type;
    }

    /**
     * @return VarType|null
     */
    protected function resolveType(): ?VarType
    {
        return $this->typeResolver->resolveFromReflection($this->reflection->getType());
    }
}
