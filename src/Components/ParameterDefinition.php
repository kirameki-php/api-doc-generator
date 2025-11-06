<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Components;

use Kirameki\ApiDocGenerator\Support\TypeResolver;
use Kirameki\ApiDocGenerator\Types\VarType;
use ReflectionParameter;

class ParameterDefinition
{
    /**
     * @var string
     */
    public string $name {
        get => $this->reflection->getName();
    }

    /**
     * @var VarType|null
     */
    public ?VarType $type {
        get => $this->type ??= $this->typeResolver->resolveFromReflection($this->reflection->getType());
    }

    /**
     * @var bool
     */
    public bool $isOptional {
        get => $this->reflection->isOptional();
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
     * @param ClassDefinition $class
     * @param MethodDefinition $method
     * @param ReflectionParameter $reflection
     * @param TypeResolver $typeResolver
     * @param PropertyDefinition|null $promotedProperty
     */
    public function __construct(
        protected readonly ClassDefinition $class,
        protected readonly MethodDefinition $method,
        protected readonly ReflectionParameter $reflection,
        protected readonly TypeResolver $typeResolver,
        protected readonly ?PropertyDefinition $promotedProperty = null,
    ) {
    }
}
