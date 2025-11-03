<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Support;

use League\CommonMark\GithubFlavoredMarkdownConverter;
use League\CommonMark\MarkdownConverter;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;

class CommentParserFactory
{
    /**
     * @var ParserConfig
     */
    protected ParserConfig $config;

    protected MarkdownConverter $markdownConverter;

    /**
     * @var Lexer
     */
    protected Lexer $lexer {
        get => $this->lexer ??= new Lexer($this->config);
    }

    /**
     * @var PhpDocParser
     */
    protected PhpDocParser $parser {
        get => $this->parser ??= new PhpDocParser(
            $this->config,
            new TypeParser($this->config, new ConstExprParser($this->config)),
            new ConstExprParser($this->config),
        );
    }

    /**
     * @param ParserConfig|null $config
     */
    public function __construct(
        ?ParserConfig $config = null,
    )
    {
        $this->config = $config ?? new ParserConfig([
            'lines' => true,
            'indexes' => true,
            'comments' => true,
        ]);

        $this->markdownConverter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }

    public function createFor(ClassFile $file): CommentParser
    {
        return new CommentParser($this->lexer, $this->parser, $file, $this->markdownConverter);
    }
}
