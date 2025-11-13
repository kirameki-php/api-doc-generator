<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Components;

use Kirameki\ApiDocGenerator\Support\CommentParser;
use Kirameki\ApiDocGenerator\Support\PhpDoc;
use function implode;

abstract class MemberInfo
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
    public PhpDoc $phpDoc {
        get => $this->phpDoc ??= $this->docParser->parse($this->comment);
    }

    public function __construct(
        protected readonly CommentParser $docParser,
    ) {
    }
}
