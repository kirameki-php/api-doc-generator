<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Support;

use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;

class CommentParserFactory
{
    /**
     * @param ParserConfig|null $config
     * @return CommentParser
     */
    public static function create(?ParserConfig $config = null): CommentParser
    {
        $config ??= new ParserConfig([
            'lines' => true,
            'indexes' => true,
            'comments' => true,
        ]);

        $lexer = new Lexer($config);
        $parser = new PhpDocParser(
            $config,
            new TypeParser($config, new ConstExprParser($config)),
            new ConstExprParser($config),
        );

        return new CommentParser($lexer, $parser);
    }
}
