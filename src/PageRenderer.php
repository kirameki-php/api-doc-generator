<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator;

use Kirameki\Storage\Path;
use Latte\Engine;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use League\CommonMark\MarkdownConverter;
use function str_replace;

class PageRenderer
{
    protected Engine $latte;
    protected ?MarkdownConverter $markdownConverter = null;

    public function __construct()
    {
        $this->latte = new Engine();
        $this->latte->setTempDirectory('/tmp');
        $this->latte->addFilter('markdown', fn(string $s) => $this->toMarkdown($s));
    }

    /**
     * @param Path $filePath
     * @param array<string, mixed> $parameters
     * @return string
     */
    public function render(Path $filePath, array $parameters = []): string
    {
        return $this->latte->renderToString($filePath->toString(), $parameters);
    }

    protected function toMarkdown(string $text): string
    {
        $this->markdownConverter ??= new GithubFlavoredMarkdownConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        return $this->markdownConverter
            ->convert(str_replace("\n", "  \n ", $text))
            ->getContent();
    }
}
