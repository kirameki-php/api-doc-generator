<?php declare(strict_types=1);

use Kirameki\ApiDocTools\DocParser;
use Kirameki\ApiDocTools\PageRenderer;

require 'vendor/autoload.php';

// raise memory limit
ini_set('memory_limit', '512M');

$app = new Kirameki\ApiDocTools\DocGenerator(
    '/Users/taka-mac/Projects/kirameki/api-doc-tools/docs',
    'vendor/kirameki/utils',
    new PageRenderer(),
    new DocParser(),
);
$app->generate();
