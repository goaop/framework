<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2024, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Proxy\Generator;

use InvalidArgumentException;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;
use PhpParser\PrettyPrinter\Standard;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

/**
 * Generates a PHP type declaration from a reflection type or type string.
 *
 * Supports all PHP 8.4 type forms:
 *   - Named types:          int, string, MyClass, self, static, never, void, mixed
 *   - Nullable:             ?int, ?MyClass
 *   - Union types:          int|string, MyClass|null
 *   - Intersection types:   Countable&Iterator
 *   - DNF types:            (Countable&Iterator)|null
 */
final class TypeGenerator
{
    private static ?Standard $printer = null;

    private Identifier|Name|NullableType|UnionType|IntersectionType $typeNode;

    private function __construct(Identifier|Name|NullableType|UnionType|IntersectionType $typeNode)
    {
        $this->typeNode = $typeNode;
    }

    /**
     * Creates a TypeGenerator from a PHP reflection type.
     */
    public static function fromReflectionType(ReflectionType $type): self
    {
        return new self(self::buildNodeFromReflection($type));
    }

    /**
     * Creates a TypeGenerator from a type string.
     *
     * Examples: 'int', '?string', 'MyClass|null', 'Countable&Iterator', '(A&B)|null'
     */
    public static function fromTypeString(string $typeStr): self
    {
        return new self(self::buildNodeFromString($typeStr));
    }

    /**
     * Returns the underlying AST node, ready for injection into a parent node.
     */
    public function getNode(): Identifier|Name|NullableType|UnionType|IntersectionType
    {
        return $this->typeNode;
    }

    /**
     * Generates the PHP type string.
     */
    public function generate(): string
    {
        return self::getPrinter()->prettyPrint([$this->typeNode]);
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    private static function buildNodeFromReflection(ReflectionType $type): Identifier|Name|NullableType|UnionType|IntersectionType
    {
        if ($type instanceof ReflectionNamedType) {
            return self::buildNamedTypeNode($type->getName(), $type->allowsNull() && $type->getName() !== 'null' && $type->getName() !== 'mixed');
        }

        if ($type instanceof ReflectionUnionType) {
            /** @var list<Identifier|IntersectionType|Name> $parts */
            $parts = [];
            foreach ($type->getTypes() as $innerType) {
                $node = self::buildNodeFromReflection($innerType);
                // Union members are always named types or intersection types (never nullable or union themselves)
                if ($node instanceof Identifier || $node instanceof Name || $node instanceof IntersectionType) {
                    $parts[] = $node;
                }
            }
            return new UnionType($parts);
        }

        if ($type instanceof ReflectionIntersectionType) {
            /** @var list<Identifier|Name> $parts */
            $parts = [];
            foreach ($type->getTypes() as $innerType) {
                $node = self::buildNodeFromReflection($innerType);
                if ($node instanceof Identifier || $node instanceof Name) {
                    $parts[] = $node;
                }
            }
            return new IntersectionType($parts);
        }

        // Fallback: use string representation
        return self::buildNodeFromString((string) $type);
    }

    private static function buildNodeFromString(string $typeStr): Identifier|Name|NullableType|UnionType|IntersectionType
    {
        $typeStr = trim($typeStr);

        // DNF type: (A&B)|C  or  (A&B)|null
        if (str_contains($typeStr, '(')) {
            return self::parseDnfType($typeStr);
        }

        // Nullable: ?T
        if (str_starts_with($typeStr, '?')) {
            $inner = self::buildNamedOrClassNode(substr($typeStr, 1));
            return new NullableType($inner);
        }

        // Union: A|B
        if (str_contains($typeStr, '|')) {
            /** @var list<Identifier|IntersectionType|Name> $parts */
            $parts = [];
            foreach (explode('|', $typeStr) as $part) {
                $node = self::buildNodeFromString(trim($part));
                if ($node instanceof Identifier || $node instanceof Name || $node instanceof IntersectionType) {
                    $parts[] = $node;
                }
            }
            return new UnionType($parts);
        }

        // Intersection: A&B
        if (str_contains($typeStr, '&')) {
            /** @var list<Identifier|Name> $parts */
            $parts = [];
            foreach (explode('&', $typeStr) as $part) {
                $parts[] = self::buildNamedOrClassNode(trim($part));
            }
            return new IntersectionType($parts);
        }

        return self::buildNamedTypeNode($typeStr, false);
    }

    /**
     * Parses a DNF type like "(Countable&Iterator)|null" into a UnionType node.
     */
    private static function parseDnfType(string $typeStr): UnionType
    {
        /** @var list<Identifier|IntersectionType|Name> $parts */
        $parts = [];
        $remaining = $typeStr;

        while ($remaining !== '') {
            $remaining = ltrim($remaining, '|');
            if ($remaining === '') {
                break;
            }
            if (str_starts_with($remaining, '(')) {
                // Intersection group
                $closeParen = strpos($remaining, ')');
                if ($closeParen === false) {
                    throw new InvalidArgumentException("Malformed DNF type: $typeStr");
                }
                $inner = substr($remaining, 1, $closeParen - 1);
                /** @var list<Identifier|Name> $intersectionParts */
                $intersectionParts = [];
                foreach (explode('&', $inner) as $p) {
                    $intersectionParts[] = self::buildNamedOrClassNode(trim($p));
                }
                $parts[] = new IntersectionType($intersectionParts);
                $remaining = substr($remaining, $closeParen + 1);
            } else {
                // Named type until next '|'
                $pipePos = strpos($remaining, '|');
                $typePart = $pipePos !== false ? substr($remaining, 0, $pipePos) : $remaining;
                $parts[] = self::buildNamedOrClassNode(trim($typePart));
                $remaining = $pipePos !== false ? substr($remaining, $pipePos) : '';
            }
        }

        return new UnionType($parts);
    }

    /**
     * Builds a named type node (Identifier for builtins, Name for class types) — never nullable.
     */
    private static function buildNamedOrClassNode(string $name): Identifier|Name
    {
        static $builtins = [
            'int', 'float', 'string', 'bool', 'array', 'callable', 'object',
            'iterable', 'void', 'null', 'never', 'mixed', 'false', 'true', 'self',
            'static', 'parent',
        ];

        return in_array(strtolower($name), $builtins, true)
            ? new Identifier(strtolower($name))
            : new Name\FullyQualified(ltrim($name, '\\'));
    }

    /**
     * Builds a named type node, optionally wrapping it in NullableType.
     */
    private static function buildNamedTypeNode(string $name, bool $nullable): Identifier|Name|NullableType
    {
        $node = self::buildNamedOrClassNode($name);

        if ($nullable) {
            return new NullableType($node);
        }

        return $node;
    }

    private static function getPrinter(): Standard
    {
        if (self::$printer === null) {
            self::$printer = new Standard(['shortArraySyntax' => true]);
        }
        return self::$printer;
    }
}
