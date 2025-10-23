<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator;

use Kirameki\Core\Exceptions\UnreachableException;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use League\CommonMark\MarkdownConverter;
use PHPStan\PhpDocParser\Ast\NodeTraverser;
use PHPStan\PhpDocParser\Ast\NodeVisitor\CloningVisitor;
use PHPStan\PhpDocParser\Ast\PhpDoc\AssertTagMethodValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\AssertTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\DeprecatedTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamOutTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ThrowsTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\CallableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\ParserConfig;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\Printer\Printer;

use ReflectionClass;
use ReflectionMethod;
use Stringable;
use function dump;
use function htmlspecialchars;
use function str_replace;

class MethodInfo
{
    /**
     * @var string
     */
    public string $name {
        get => $this->reflection->getName();
    }

    public string $comment {
        get => $this->comment ??= (string) $this->reflection->getDocComment();
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
     * @var MarkdownConverter|null
     */
    private ?MarkdownConverter $converter = null;

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
     * @param ReflectionClass<object> $reflectionClass
     * @param ReflectionMethod $reflection
     * @param CommentParser $docParser
     */
    public function __construct(
        protected ReflectionClass $reflectionClass,
        protected ReflectionMethod $reflection,
        protected CommentParser $docParser,
    ) {
    }

    /**
     * @return string
     */
    public function commentAsMarkdown(): string
    {
        if ($this->comment === '') {
            return '';
        }
        $content = '';
        $docNode = $this->docParser->parse($this->comment);
        foreach ($docNode->children as $child) {
            if ($child instanceof PhpDocTextNode) {
                $content .= $this->descriptionAsMarkdown($child->text);
            }
            if ($child instanceof PhpDocTagNode) {
                $inner = '';
                $value = $child->value;
                if ($value instanceof ParamTagValueNode) {
                    $type = $value->type;
                    if ($type instanceof IdentifierTypeNode) {
                        $inner .= $this->paramAsHtml($type, $value);
                    } elseif ($type instanceof UnionTypeNode) {
                        $inner .= $this->paramAsHtml($type, $value);
                    } elseif ($type instanceof CallableTypeNode) {
                        $inner .= $this->paramAsHtml($type, $value);
                    } elseif ($type instanceof GenericTypeNode) {
                        $inner .= $this->paramAsHtml($type, $value);
                    } elseif ($type instanceof ArrayShapeNode) {
                        $inner .= $this->paramAsHtml($type, $value);
                    } else {
                        dump($value);
                        throw new UnreachableException();
                    }
                } elseif ($value instanceof TemplateTagValueNode) {
                    $inner .= '<div class="phpdoc-type">' . $this->toHtml($value->name) . '</div>';
                } elseif ($value instanceof ReturnTagValueNode) {
                    $inner .= ' <span class="phpdoc-return">';
                    $inner .= $this->toHtml($value->type);
                    $inner .= '</span>';
                    $inner .= $this->descriptionAsMarkdown($value->description);
                } elseif ($value instanceof GenericTagValueNode) {
                    if ($value->value !== '') {
                        $inner .= '<div class="phpdoc-type">' . $this->toHtml($value) . '</div>';
                    }
                } elseif ($value instanceof ParamOutTagValueNode) {
                    $inner .= $this->paramOutAsHtml($value->type, $value);
                } elseif ($value instanceof ThrowsTagValueNode) {
                    $inner .= '<div class="phpdoc-type">' . $this->toHtml($value->type) . '</div>';
                    $inner .= $this->descriptionAsMarkdown($value->description);
                } elseif ($value instanceof AssertTagMethodValueNode) {
                    $inner .= '<div class="phpdoc-assert">' . $value . '</div>';
                } elseif ($value instanceof AssertTagValueNode) {
                    $inner .= '<div class="phpdoc-assert">' . $value . '</div>';
                } elseif ($value instanceof DeprecatedTagValueNode) {
                    $inner .= '<div class="phpdoc-deprecated">' . $value . '</div>';
                } else {
                    dump($this->reflectionClass->name . '::' . $this->reflection->name);
                    dump($value);
                    throw new UnreachableException();
                }
                $content .= '<div class="phpdoc-tag">';
                $content .= '<span class="phpdoc-tag-name">' . $child->name . '</span>';
                $content .= $inner;
                $content .= '</div>';
            }
        }
        return $content;
    }

    /**
     * @return MarkdownConverter
     */
    protected function getMarkdownConverter(): MarkdownConverter
    {
        return $this->converter ??= new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    protected function toMarkdown(string $text): string
    {
        return $this->getMarkdownConverter()
            ->convert(str_replace("\n", "  \n ", $text))
            ->getContent();
    }

    protected function toHtml(string|Stringable $text): string
    {
        return htmlspecialchars((string) $text);
    }

    protected function paramAsHtml(TypeNode $type, ParamTagValueNode $value): string
    {
        $content = '';
        $content .= ' <span class="phpdoc-type">' . $this->toHtml($type) . '</span>';
        $content .= ' <span class="phpdoc-var-name">';
        if ($value->isVariadic) {
            $content .= '...';
        }
        if ($value->isReference) {
            $content .= '&';
        }
        $content .= $value->parameterName;
        $content .= '</span>';
        $content .= $this->descriptionAsMarkdown($value->description);
        return $content;
    }

    protected function paramOutAsHtml(TypeNode $type, ParamOutTagValueNode $value): string
    {
        $content = '';
        $content .= ' <span class="phpdoc-type">' . $type . '</span>';
        $content .= ' <span class="phpdoc-var-name">' . $value->parameterName . '</span>';
        $content .= $this->descriptionAsMarkdown($value->description);
        return $content;
    }

    protected function descriptionAsMarkdown(string $description): string
    {
        if ($description === '') {
            return '';
        }
        return '<div class="phpdoc-description">' . $this->toMarkdown($description) . '</div>';
    }
}
