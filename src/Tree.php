<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator;

class Tree
{
    /**
     * @var array<string, mixed>
     */
    protected array $directories = [];

    /**
     * @var list<string>
     */
    protected array $files = [];

    /**
     * @param string $directory
     * @return Tree
     */
    public function getDirectory(string $directory): Tree
    {
        return $this->directories[$directory] ??= new Tree();
    }

    /**
     * @param string $file
     * @return void
     */
    public function addFile(string $file): void
    {
        $this->files[] = $file;
    }
}
