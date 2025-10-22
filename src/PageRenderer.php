<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator;

use Kirameki\Storage\Path;
use Latte\Engine;

class PageRenderer
{
    /**
     * @param Path $filePath
     * @param array<string, mixed> $parameters
     * @return string
     */
    public function render(Path $filePath, array $parameters = []): string
    {
        $latte = new Engine();
        $latte->setTempDirectory('/tmp');

        return $latte->renderToString($filePath->toString(), $parameters);
    }
}
