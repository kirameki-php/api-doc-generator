<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Support;

use PHPStan\PhpDocParser\Ast\PhpDoc\ExtendsTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ImplementsTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ThrowsTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;

class PhpDoc
{
    /**
     * @param list<TemplateTagValueNode> $templates
     * @param ExtendsTagValueNode|null $extends
     * @param list<ImplementsTagValueNode> $implements
     * @param ReturnTagValueNode|null $return
     * @param VarTagValueNode|null $var
     * @param array<string, ParamTagValueNode> $params
     * @param list<ThrowsTagValueNode> $throws
     * @param list<string> $texts
     */
    public function __construct(
        public readonly array $templates,
        public readonly ?ExtendsTagValueNode $extends,
        public readonly array $implements,
        public readonly ?VarTagValueNode $var,
        public readonly ?ReturnTagValueNode $return,
        public readonly array $params,
        public readonly array $throws,
        public readonly array $texts,
    ) {
    }
}
