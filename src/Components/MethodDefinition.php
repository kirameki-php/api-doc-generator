<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Components;

use Kirameki\ApiDocGenerator\Support\CommentParser;
use Kirameki\ApiDocGenerator\Support\TypeResolver;
use Kirameki\ApiDocGenerator\Types\VarType;
use Kirameki\Core\Exceptions\UnreachableException;
use Kirameki\Text\Str;
use Kirameki\Text\Utf8;
use ReflectionMethod;
use ReflectionParameter;
use function dump;

class MethodDefinition extends MemberDefinition
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
    public string $comment {
        get => $this->comment ??= (string) $this->reflection->getDocComment();
    }

    /**
     * @var list<ParameterDefinition>
     */
    public array $parameters {
        get => $this->parameters ??= array_map(
            fn (ReflectionParameter $param) => new ParameterDefinition($this->class, $this, $param, $this->typeResolver),
            $this->reflection->getParameters()
        );
    }

    /**
     * @var VarType
     */
    public VarType $returnType {
        get => $this->returnType ??= $this->resolveReturnType();
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
    public bool $isStatic {
        get => $this->reflection->isStatic();
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
     * @var string
     */
    public string $id {
        get => 'method-' . Str::toKebabCase($this->reflection->getName());
    }

    /**
     * @param ClassDefinition $class
     * @param ReflectionMethod $reflection
     * @param CommentParser $docParser
     * @param TypeResolver $typeResolver
     */
    public function __construct(
        protected ClassDefinition $class,
        protected ReflectionMethod $reflection,
        CommentParser $docParser,
        protected TypeResolver $typeResolver,
    ) {
        parent::__construct($docParser);
    }

    /**
     * @return VarType
     */
    protected function resolveReturnType(): VarType
    {
        return $this->phpDoc->return !== null
            ? $this->typeResolver->resolveFromNode($this->phpDoc->return->type, $this->phpDoc)
            : $this->typeResolver->resolveFromReflection($this->reflection->getReturnType());
    }
}
