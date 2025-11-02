<?php declare(strict_types=1);

namespace Kirameki\ApiDocGenerator\Support;

use Kirameki\ApiDocGenerator\Types\IntersectionVarType;
use Kirameki\ApiDocGenerator\Types\NamedVarType;
use Kirameki\ApiDocGenerator\Types\UnionVarType;
use Kirameki\ApiDocGenerator\Types\VarType;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Stringable;
use function array_map;
use function class_exists;
use function enum_exists;
use function interface_exists;
use function is_string;
use function trait_exists;

final class StructureUtil
{
    /**
     * Resolves the fully qualified class name from a given name within the context of a class file.
     *
     * @param ClassFile $file
     * @param string|Stringable $name
     * @return string|null
     */
    public static function getFullyQualifiedName(
        ClassFile $file,
        string|Stringable $name,
    ): ?string
    {
        if ($name instanceof Stringable) {
            $name = (string) $name;
        }

        // When the type is a fully qualified class name
        if (class_exists($name) || interface_exists($name) || trait_exists($name) || enum_exists($name)) {
            return $name;
        }

        // When the type is imported via use statement
        $class = $file->imports[$name] ?? null;
        if (is_string($class)) {
            return $class;
        }

        // When the type is a sibling class in the same namespace
        /** @var class-string $sibling */
        $sibling = $file->reflection->getNamespaceName() . '\\' . $name;
        if (class_exists($sibling) || interface_exists($sibling) || trait_exists($sibling) || enum_exists($sibling)) {
            return $sibling;
        }

        return null;
    }


    /**
     * @param ReflectionType|null $type
     * @return VarType
     */
    public static function reflectionToInfo(?ReflectionType $type): VarType
    {
        if ($type instanceof ReflectionIntersectionType) {
            return new IntersectionVarType(
                array_map(self::reflectionToInfo(...), $type->getTypes()),
                $type->allowsNull()
            );
        }

        if ($type instanceof ReflectionUnionType) {
            return new UnionVarType(
                array_map(self::reflectionToInfo(...), $type->getTypes()),
                $type->allowsNull()
            );
        }

        if ($type instanceof ReflectionNamedType) {
            return new NamedVarType($type->getName(), $type->allowsNull());
        }

        return new NamedVarType('mixed', false);
    }
}
