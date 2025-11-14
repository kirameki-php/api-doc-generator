<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Components;

use Kirameki\ApiDocGenerator\Support\CommentParser;
use Kirameki\ApiDocGenerator\Support\TypeResolver;
use Kirameki\ApiDocGenerator\Types\VarType;
use Kirameki\Core\Exceptions\UnreachableException;
use Kirameki\Text\Str;
use ReflectionMethod;
use ReflectionParameter;

class MethodInfo extends MemberInfo
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
     * @var list<ParameterInfo>
     */
    public array $parameters {
        get => $this->parameters ??= array_map(
            fn (ReflectionParameter $param) => new ParameterInfo($this->class, $this, $param, $this->typeResolver),
            $this->reflection->getParameters(),
        );
    }

    /**
     * @var VarType
     */
    public VarType $returnType {
        get => $this->returnType ??= $this->resolveReturnType();
    }

    /**
     * @var VarType
     */
    public VarType $returnDocType {
        get => $this->returnDocType ??= $this->resolveReturnDocType();
    }

    /**
     * @var string
     */
    public string $returnDescription {
        get => $this->returnDescription ??= $this->phpDoc->return->description ?? '';
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
     * @var list<TemplateInfo>
     */
    public array $templates {
        get => $this->templates ??= $this->resolveTemplates();
    }

    /**
     * @var list<VarType>
     */
    public array $throws {
        get => $this->throws ??= $this->resolveThrows();
    }

    /**
     * @var string
     */
    public string $id {
        get => 'method-' . Str::toKebabCase($this->reflection->getName());
    }

    /**
     * @param ClassInfo $class
     * @param ReflectionMethod $reflection
     * @param CommentParser $docParser
     * @param TypeResolver $typeResolver
     */
    public function __construct(
        protected ClassInfo $class,
        protected ReflectionMethod $reflection,
        CommentParser $docParser,
        protected TypeResolver $typeResolver,
    ) {
        parent::__construct($docParser);
    }

    /**
     * @return list<TemplateInfo>
     */
    protected function resolveTemplates(): array
    {
        $templates = [];
        foreach ($this->phpDoc->templates as $tpl) {
            $bound = $tpl->bound
                ? $this->typeResolver->resolveFromNode($tpl->bound, $this->phpDoc)
                : null;
            $default = $tpl->default ? $this->typeResolver->resolveFromNode($tpl->default, $this->phpDoc) : null;
            $templates[] = new TemplateInfo(
                $tpl->name,
                $bound,
                $default,
            );
        }
        return $templates;
    }

    /**
     * @return list<VarType>
     */
    protected function resolveThrows(): array
    {
        $throws = [];
        foreach ($this->phpDoc->throws as $throw) {
            $throws[] = $this->typeResolver->resolveFromNode($throw->type, $this->phpDoc);
        }
        return $throws;
    }

    /**
     * @return VarType
     */
    protected function resolveReturnDocType(): VarType
    {
        return $this->phpDoc->return !== null
            ? $this->typeResolver->resolveFromNode($this->phpDoc->return->type, $this->phpDoc)
            : $this->returnType;
    }

    protected function resolveReturnType(): VarType
    {
        return $this->typeResolver->resolveFromReflection($this->reflection->getReturnType());
    }
}
