<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator;

use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;

class DocParser
{
    /**
     * @var ParserConfig
     */
    protected ParserConfig $config;

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
            new ConstExprParser($this->config)
        );
    }

    public function __construct(
        ?ParserConfig $config = null,
    )
    {
        $this->config = $config ?? new ParserConfig([
            'lines' => true,
            'indexes' => true,
            'comments' => true,
        ]);
    }

    /**
     * @param string $comment
     * @return PhpDocNode
     */
    public function parse(string $comment): PhpDocNode
    {
        $tokens = new TokenIterator($this->lexer->tokenize($comment));
        return $this->parser->parse($tokens);
    }
}
