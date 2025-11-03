<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Components;

use Kirameki\ApiDocGenerator\Support\CommentParser;
use Kirameki\ApiDocGenerator\Support\PhpDoc;
use Kirameki\Core\Exceptions\UnreachableException;
use PHPStan\PhpDocParser\Ast\PhpDoc\AssertTagMethodValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\AssertTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\DeprecatedTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamOutTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ThrowsTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\CallableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use Stringable;
use function dump;
use function htmlspecialchars;
use function implode;
use function str_replace;

abstract class MemberDefinition
{
    /**
     * @var string
     */
    abstract public string $name {
        get;
    }

    /**
     * @var string
     */
    abstract public string $comment {
        get;
    }

    /**
     * @var bool
     */
    abstract public bool $isFinal {
        get;
    }

    /**
     * @var bool
     */
    abstract public bool $isAbstract {
        get;
    }

    /**
     * @var bool
     */
    abstract public bool $isStatic {
        get;
    }

    /**
     * @var Visibility
     */
    abstract public Visibility $visibility {
        get;
    }

    /**
     * @var PhpDoc
     */
    protected PhpDoc $phpDoc {
        get => $this->phpDoc ??= $this->docParser->parse($this->comment);
    }

    public function __construct(
        protected readonly CommentParser $docParser,
    ) {
    }

    /**
     * @return string
     */
    public function commentAsMarkdown(): string
    {
        return implode("\n", $this->phpDoc->texts);
        $docNode = $this->docParser->parse($this->comment);
        foreach ($docNode->children as $child) {
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
                        throw new UnreachableException();
                    }
                } elseif ($value instanceof GenericTagValueNode) {
                    if ($value->value !== '') {
                        $inner .= '<span class="phpdoc-type">' . $this->toHtml($value) . '</span>';
                    }
                } elseif ($value instanceof ParamOutTagValueNode) {
                    $inner .= $this->paramOutAsHtml($value->type, $value);
                } elseif ($value instanceof ThrowsTagValueNode) {
                    $inner .= '<span class="phpdoc-type">' . $this->toHtml($value->type) . '</span>';
                    $inner .= $this->descriptionAsMarkdown($value->description);
                } elseif ($value instanceof AssertTagMethodValueNode) {
                    $inner .= '<div class="phpdoc-assert">' . $value . '</div>';
                } elseif ($value instanceof AssertTagValueNode) {
                    $inner .= '<div class="phpdoc-assert">' . $value . '</div>';
                } elseif ($value instanceof DeprecatedTagValueNode) {
                    $inner .= '<div class="phpdoc-deprecated">' . $value . '</div>';
                } elseif ($value instanceof VarTagValueNode) {
                    $inner .= ' <span class="phpdoc-type">';
                    $inner .= ($value->type instanceof UnionTypeNode)
                        ? $this->fixUnionOutput($this->toHtml($value->type))
                        : $this->toHtml($value->type);
                    $inner .= '</span>';
                } else {
                    dump($value);
                    throw new UnreachableException();
                }
                $content .= '<div class="phpdoc-tag">';
                $content .= '<span class="phpdoc-tag-name">' . $child->name . '</span> ';
                $content .= $inner;
                $content .= '</div>';
            }
        }
        return $content;
    }

    protected function toHtml(string|Stringable $text): string
    {
        return htmlspecialchars((string) $text);
    }

    protected function paramAsHtml(TypeNode $type, ParamTagValueNode $value): string
    {
        $typeHtml = $this->toHtml($type);
        if ($type instanceof UnionTypeNode) {
            $typeHtml = $this->fixUnionOutput($typeHtml);
        }

        $content = '';
        $content .= ' <span class="phpdoc-type">' . $typeHtml . '</span>';
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

    protected function fixUnionOutput(string $text): string
    {
        return str_replace(' | ', '|', $text);
    }
}
