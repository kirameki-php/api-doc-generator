<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Components;

use Kirameki\Core\Exceptions\UnreachableException;
use Kirameki\Text\Str;
use ReflectionClassConstant;
use ReflectionNamedType;

class ConstantInfo
{
    /**
     * @var string
     */
    public string $name {
        get => $this->reflection->name;
    }

    /**
     * @var string
     */
    public string $namespace {
        get => $this->structure->namespace;
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
        get => $this->reflection->isFinal ?? false;
    }

    /**
     * @var string|null
     */
    public ?string $type {
        get => $this->type ??= $this->resolveType();
    }

    /**
     * @var Visibility
     */
    public Visibility $visibility {
        get => match (true) {
            $this->reflection->isPublic() => Visibility::Public,
            $this->reflection->isProtected() => Visibility::Protected,
            $this->reflection->isPrivate() => Visibility::Private,
            default => throw new UnreachableException(),
        };
    }

    /**
     * @var mixed
     */
    public mixed $value {
        get => $this->reflection->getValue();
    }

    /**
     * @param StructureInfo $structure
     * @param ReflectionClassConstant $reflection
     */
    public function __construct(
        protected StructureInfo $structure,
        protected ReflectionClassConstant $reflection,
    ) {
    }

    protected function resolveType(): ?string
    {
        $refType = $this->reflection->getType();
        if ($refType instanceof ReflectionNamedType) {
            return $refType->getName();
        }
        return null;
    }
}
