<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Support;

use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\ParserFactory;
use ReflectionClass;
use UnitEnum;
use function array_map;
use function class_exists;
use function file_get_contents;
use function implode;
use function interface_exists;
use function trait_exists;

class PhpFile
{
    /**
     * @param ReflectionClass<object>|ReflectionClass<UnitEnum> $reflection
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

        $parser = new ParserFactory()->createForNewestSupportedVersion();
        foreach ($parser->parse($code) ?? [] as $node) {
            if ($node instanceof Namespace_) {
                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof Use_) {
                        foreach ($stmt->uses as $use) {
                            $class = $use->name->toString();
                            $alias = $use->getAlias()->toString();
                            if (class_exists($class) || interface_exists($class) || trait_exists($class)) {
                                $this->imports[$alias] = $class;
                            }
                        }
                    }
                    if ($stmt instanceof Class_) {
                        if ($stmt->implements !== []) {
                            foreach ($stmt->implements as $implement) {
                                $interface = $implement->toString();
                                if (interface_exists($interface)) {
                                    $this->implements[] = $interface;
                                }
                            }
                        }
                        foreach ($stmt->stmts ?? [] as $classStmt) {
                            if ($classStmt instanceof TraitUse) {
                                /** @var class-string $trait */
                                $trait = $classStmt->traits[0]->toString();
                                $this->traits[$trait] = implode(', ', array_map(
                                    fn($c) => $c->getText(),
                                    $classStmt->getComments(),
                                ));
                            }
                        }
                    }
                }
            }
        }
    }
}
