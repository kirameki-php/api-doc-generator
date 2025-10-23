<?php declare(strict_types=1);

use Kirameki\ApiDocGenerator\CommentParser;
use Kirameki\ApiDocGenerator\PageRenderer;

require 'vendor/autoload.php';

// raise memory limit
ini_set('memory_limit', '512M');

$app = new Kirameki\ApiDocGenerator\DocGenerator(
    '/Users/taka-mac/Projects/kirameki/api-doc-generator/docs',
    'vendor/kirameki/utils',
    new PageRenderer(),
    new CommentParser(),
);
$app->generate();
