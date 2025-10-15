<?php declare(strict_types=1);

require 'vendor/autoload.php';

$app = new Kirameki\ApiDocTools\DocGenerator('vendor/kirameki/utils');
$app->generate();
