<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Support;

use PHPStan\PhpDocParser\Ast\PhpDoc\DeprecatedTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ExtendsTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ImplementsTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamOutTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ThrowsTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\UsesTagValueNode;
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
     * @param array<string, ParamOutTagValueNode> $paramOuts
     * @param list<ThrowsTagValueNode> $throws
     * @param UsesTagValueNode|null $use
     * @param list<string> $texts
     * @param DeprecatedTagValueNode|null $deprecated
     */
    public function __construct(
        public readonly array $templates,
        public readonly ?ExtendsTagValueNode $extends,
        public readonly array $implements,
        public readonly ?VarTagValueNode $var,
        public readonly ?ReturnTagValueNode $return,
        public readonly array $params,
        public readonly array $paramOuts,
        public readonly array $throws,
        public readonly ?UsesTagValueNode $use,
        public readonly array $texts,
        public readonly ?DeprecatedTagValueNode $deprecated,
    ) {
    }
}
