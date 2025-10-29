<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Components;

enum Visibility: string
{
    case Public = 'public';
    case Protected = 'protected';
    case Private = 'private';
    case PublicPrivateSet = 'public private(set)';
    case ProtectedPrivateSet = 'protected private(set)';
}
