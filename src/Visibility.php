<?php declare(strict_types=1);

namespace Kirameki\ApiDocTools;

enum Visibility: string
{
    case Public = 'public';
    case Protected = 'protected';
    case Private = 'private';
    case PublicPrivateSet = 'public private(set)';
}
