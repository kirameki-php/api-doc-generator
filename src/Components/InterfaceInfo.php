<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Components;

use Kirameki\ApiDocGenerator\Support\CommentParser;
use Kirameki\ApiDocGenerator\Support\PhpFile;
use Kirameki\ApiDocGenerator\Support\TypeResolver;
use Kirameki\ApiDocGenerator\Support\UrlResolver;
use ReflectionClass;

class InterfaceInfo extends ClassInfo
{
    /**
     * @var string
     */
    public string $type = 'interface';

    /**
     * @var bool
     */
    public bool $isAbstract {
        get => false;
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @param PhpFile $file
     * @param CommentParser $docParser
     * @param UrlResolver $urlResolver
     * @param TypeResolver|null $typeResolver
     */
    public function __construct(
        ReflectionClass $reflection,
        PhpFile $file,
        CommentParser $docParser,
        UrlResolver $urlResolver,
        ?TypeResolver $typeResolver = null,
    ) {
        parent::__construct(
            $reflection,
            $file,
            $docParser,
            $urlResolver,
            $typeResolver,
        );
    }

}
