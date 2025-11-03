<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Support;

use Kirameki\Core\Exceptions\UnreachableException;
use League\CommonMark\MarkdownConverter;
use PHPStan\PhpDocParser\Ast\PhpDoc\ExtendsTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ImplementsTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use function dump;
use function str_replace;

class CommentParser
{
    /**
     * @param Lexer $lexer
     * @param PhpDocParser $parser
     * @param ClassFile $file
     * @param MarkdownConverter $markdownConverter
     */
    public function __construct(
        protected Lexer $lexer,
        protected PhpDocParser $parser,
        protected ClassFile $file,
        protected MarkdownConverter $markdownConverter,
    ) {
    }

    /**
     * @param string $comment
     * @return PhpDoc
     */
    public function parse(string $comment): PhpDoc
    {
        if ($comment === '') {
            $comment = '/** */';
        }

        $tokens = new TokenIterator($this->lexer->tokenize($comment));
        $nodes = $this->parser->parse($tokens)->children;

        $templates = [];
        $extends = null;
        $implements = [];
        $return = null;
        $var = null;
        $texts = [];

        foreach ($nodes as $node) {
            if ($node instanceof PhpDocTagNode) {
                $val = $node->value;
                match (true) {
                    $val instanceof TemplateTagValueNode => $templates[] = $val,
                    $val instanceof ImplementsTagValueNode => $implements[] = $val,
                    $val instanceof ExtendsTagValueNode => $extends = $val,
                    $val instanceof ReturnTagValueNode => $return = $val,
                    $val instanceof VarTagValueNode => $var = $val,
                    default => null,
                };
            } elseif ($node instanceof PhpDocTextNode) {
                $texts[]= $this->toMarkdown($node->text);
            } else {
                throw new UnreachableException();
            }
        }
        return new PhpDoc(
            $templates,
            $extends,
            $implements,
            $return,
            $var,
            $texts,
        );
    }

    protected function toMarkdown(string $text): string
    {
        return $this->markdownConverter
            ->convert(str_replace("\n", "  \n ", $text))
            ->getContent();
    }
}
