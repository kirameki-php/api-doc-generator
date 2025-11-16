<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Support;

use ReflectionClass;
use function array_key_exists;
use function class_exists;
use function count;
use function dump;
use function file_get_contents;
use function in_array;
use function interface_exists;
use function is_array;
use function preg_split;
use function trait_exists;
use function trim;
use const T_CLASS;
use const T_ENUM;
use const T_IMPLEMENTS;
use const T_STRING;
use const T_TRAIT;

class PhpFile
{
    /**
     * @param ReflectionClass<object> $reflection
     * @param list<class-string> $implements
     * @param array<string, class-string> $imports
     * @param array<class-string, string> $traits
     */
    public function __construct(
        public ReflectionClass $reflection,
        public array $imports = [],
        public array $implements = [],
        public array $traits = [],
    ) {
        $this->evaluate();
    }

    /**
     * @return void
     */
    protected function evaluate(): void
    {
        $filePath = $this->reflection->getFileName();
        if ($filePath === false) {
            return;
        }

        $code = file_get_contents($filePath);
        if ($code === false) {
            return;
        }

        $inOuterBlock = true;
        $tokens = token_get_all($code, TOKEN_PARSE);

        foreach($tokens as $i => $token) {
            if (!is_array($token)) {
                continue;
            }

            if ($inOuterBlock && in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                $inOuterBlock = false;
                continue;
            }

            if ($token[0] === T_IMPLEMENTS) {
                $j = $i + 1;
                while (($curr = $tokens[$j] ?? null) && $tokens[$j] !== '{') {
                    if (is_array($curr)) {
                        if ($curr[0] === T_STRING && interface_exists($curr[1])) {
                            $this->implements[] = $curr[1];
                        }
                    }
                    $j++;
                }
            }

            if ($token[0] === T_USE) {
                if ($inOuterBlock) {
                    $useStatement = '';
                    $j = $i + 1;
                    while (isset($tokens[$j]) && $tokens[$j] !== ';') {
                        if (is_array($tokens[$j])) {
                            $useStatement .= $tokens[$j][1];
                        } else {
                            $useStatement .= $tokens[$j];
                        }
                        $j++;
                    }
                    /** @var list<string> $useParts */
                    $useParts = preg_split('/\s+as\s+/i', trim($useStatement));
                    $class = trim($useParts[0]);
                    if (count($useParts) === 2) {
                        $alias = trim($useParts[1]);
                    } else {
                        $alias = $class;
                    }
                    if (class_exists($class) || interface_exists($class) || trait_exists($class)) {
                        $this->imports[$alias] = $class;
                    }
                } else {
                    if ($tokens[$i + 2][0] === T_STRING) {
                        $trait = $tokens[$i + 2][1] ?? '';
                        if (!trait_exists($trait) && array_key_exists($trait, $this->imports)) {
                            $trait = $this->imports[$trait];
                        }
                        if (!trait_exists($trait)) {
                            $trait = $this->reflection->getNamespaceName() . '\\' . $trait;
                        }
                        if (trait_exists($trait)) {
                            $comment = trim($tokens[$i - 2][1] ?? '');
                            if (str_starts_with($comment, '/**')) {
                                $this->traits[$trait] = $comment;
                            }
                        }
                    }
                }
            }
        }
    }
}
