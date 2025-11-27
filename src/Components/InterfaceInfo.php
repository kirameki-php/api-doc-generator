<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Components;

use Kirameki\ApiDocGenerator\Support\CommentParser;
use Kirameki\ApiDocGenerator\Support\TypeResolver;
use Kirameki\ApiDocGenerator\Support\UrlResolver;
use ReflectionClass;

class InterfaceInfo extends ClassInfo
{
    /**
     * @var string
     */
    public string $type {
        get => 'interface';
    }

    /**
     * @var bool
     */
    public bool $isAbstract {
        get => false;
    }

    /**
     * @param ReflectionClass<covariant object> $reflection
     * @param CommentParser $docParser
     * @param UrlResolver $urlResolver
     * @param TypeResolver $typeResolver
     */
    public function __construct(
        ReflectionClass $reflection,
        CommentParser $docParser,
        UrlResolver $urlResolver,
        TypeResolver $typeResolver,
    ) {
        parent::__construct(
            $reflection,
            $docParser,
            $urlResolver,
            $typeResolver,
        );
    }

}
