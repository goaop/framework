<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Proxy\Generator;

use Go\Aop\Exception\WeavingException;
use PhpParser\Node\ComplexType;
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
    /** @var list<string> */
    private const array BUILTIN_TYPES = [
        'int', 'float', 'string', 'bool', 'array', 'callable', 'object',
        'iterable', 'void', 'null', 'never', 'mixed', 'false', 'true', 'self',
        'static', 'parent',
    ];

    private static ?Standard $printer = null;

    private function __construct(
        private readonly Identifier|Name|NullableType|UnionType|IntersectionType $typeNode,
    ) {}

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
     * Creates a TypeGenerator from a raw PHP-Parser AST type node.
     *
     * Use this when you have direct access to the AST (e.g. from parser-reflection's getNode()),
     * which preserves keyword type hints such as 'self', 'parent', and 'static' exactly as
     * declared in the source — without PHP 8.5+ name resolution applied by reflection APIs.
     */
    public static function fromAstNode(Identifier|Name|NullableType|UnionType|IntersectionType $node): self
    {
        return new self($node);
    }

    /**
     * Creates a TypeGenerator from a type node of a parsed declaration, e.g. parser-reflection's getNode()
     *
     * Produces the same node as resolving the declared type to a reflection type without a class context and
     * converting it back with {@see fromReflectionType()}, without that round trip:
     *  - class names are fully qualified through their 'resolvedName' attribute set by the NameResolver;
     *  - builtin types and the 'self', 'parent' and 'static' keywords are kept as declared, in lower case;
     *  - nullable, union, intersection and DNF types keep their structure.
     */
    public static function fromResolvedAstNode(Identifier|Name|ComplexType $node): self
    {
        return new self(self::buildNodeFromAst($node));
    }

    /**
     * Returns the type with every class name referenced through the imports of the generated file
     *
     * Builtin types and the `self`, `parent` and `static` keywords stay as they are; nullable, union,
     * intersection and DNF types keep their structure.
     */
    public function withImportedNames(?ProxyImports $imports): self
    {
        return $imports !== null ? new self(self::importNames($this->typeNode, $imports)) : $this;
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

    private static function importNames(
        Identifier|Name|NullableType|UnionType|IntersectionType $node,
        ProxyImports $imports,
    ): Identifier|Name|NullableType|UnionType|IntersectionType {
        if ($node instanceof NullableType) {
            return new NullableType(self::importNamedType($node->type, $imports));
        }
        if ($node instanceof UnionType) {
            return new UnionType(array_map(
                static fn(Identifier|Name|IntersectionType $type) => $type instanceof IntersectionType
                    ? self::importIntersectionType($type, $imports)
                    : self::importNamedType($type, $imports),
                $node->types,
            ));
        }
        if ($node instanceof IntersectionType) {
            return self::importIntersectionType($node, $imports);
        }

        return self::importNamedType($node, $imports);
    }

    private static function importIntersectionType(IntersectionType $node, ProxyImports $imports): IntersectionType
    {
        return new IntersectionType(array_map(
            static fn(Identifier|Name $type) => self::importNamedType($type, $imports),
            $node->types,
        ));
    }

    /**
     * Builtin types and the `self`, `parent` and `static` keywords are kept, class names use their alias
     */
    private static function importNamedType(Identifier|Name $node, ProxyImports $imports): Identifier|Name
    {
        if ($node instanceof Identifier || $node->isSpecialClassName()) {
            return $node;
        }

        return new Name($imports->import($node->toString()));
    }

    /**
     * Returns the original type hint name from a ReflectionNamedType, preserving 'self', 'parent', and 'static'
     * even on PHP 8.5+ where ReflectionNamedType::getName() resolves them to the actual class name.
     */
    public static function resolveReflectionNamedTypeName(ReflectionNamedType $type): string
    {
        // PHP 8.5 added isSelf()/isParent() and changed getName() to return the resolved class name.
        if (method_exists($type, 'isSelf') && $type->isSelf() === true) {
            return 'self';
        }
        if (method_exists($type, 'isParent') && $type->isParent() === true) {
            return 'parent';
        }

        return $type->getName();
    }

    /**
     * Renders a PhpParser AST type node as a phpDoc type string.
     *
     * Use this instead of renderTypeForPhpDoc() whenever the raw AST node is available
     * (e.g. via ReflectionMethod::getNode()->getReturnType()). This avoids the PHP 8.5+
     * behaviour where ReflectionNamedType::getName() resolves 'self'/'parent' to the
     * actual FQCN, and instead preserves the type exactly as declared in the source.
     *
     * The `ComplexType` parameter covers NullableType, UnionType, and IntersectionType —
     * the three composite type node classes that PhpParser's ClassMethod/Property AST nodes
     * report via their `returnType`/`type` properties.
     *
     * - Identifier nodes (int, string, void, etc.) are returned as-is.
     * - Keyword Name nodes (self, parent, static) are returned as-is.
     * - Other Name nodes are rendered as FQCN with a leading backslash, using the
     *   'resolvedName' attribute set by PhpParser's NameResolver when available.
     * - NullableType: `?InnerType`.
     * - UnionType / IntersectionType: members joined with `|` / `&`.
     * - null input returns `mixed`.
     */
    public static function renderAstTypeForPhpDoc(
        Identifier|Name|ComplexType|null $node,
        ?ProxyImports $imports = null,
    ): string {
        if ($node === null) {
            return 'mixed';
        }

        if ($node instanceof Identifier) {
            return $node->name;
        }

        if ($node instanceof Name) {
            $name = $node->toString();
            // Keyword types are preserved as-is (never resolved to FQCN)
            if (in_array($name, ['self', 'parent', 'static'], true)) {
                return $name;
            }
            // Use the resolved FQCN added by NameResolver when available
            if ($node->hasAttribute('resolvedName') && ($resolved = $node->getAttribute('resolvedName')) instanceof Name) {
                $name = $resolved->toString();
            }

            return self::renderClassNameForPhpDoc($name, $imports);
        }

        $renderInner = static fn(Identifier|Name|ComplexType $type): string => self::renderAstTypeForPhpDoc($type, $imports);
        if ($node instanceof NullableType) {
            return '?' . $renderInner($node->type);
        }

        if ($node instanceof UnionType) {
            return implode('|', array_map($renderInner, $node->types));
        }

        if ($node instanceof IntersectionType) {
            return implode('&', array_map($renderInner, $node->types));
        }

        return 'mixed';
    }

    /**
     * Renders a ReflectionType as a phpDoc type string suitable for use in @var or @template annotations.
     *
     * - Builtin types and keywords (self, static, parent) are returned as-is.
     * - Class types are prefixed with a leading backslash (FQCN).
     * - Nullable types are rendered as `?Type` (only for simple named types, not for union with null).
     * - Union types are rendered as `Type1|Type2`.
     * - Intersection types are rendered as `Type1&Type2`.
     * - A null $type renders as `mixed`.
     */
    public static function renderTypeForPhpDoc(?ReflectionType $type, ?ProxyImports $imports = null): string
    {
        if ($type === null) {
            return 'mixed';
        }

        if ($type instanceof ReflectionNamedType) {
            $name = self::resolveReflectionNamedTypeName($type);

            if (!$type->isBuiltin() && !in_array($name, ['self', 'static', 'parent'], true)) {
                $name = self::renderClassNameForPhpDoc($name, $imports);
            }

            if ($type->allowsNull() && $name !== 'mixed' && $name !== 'null') {
                return '?' . $name;
            }

            return $name;
        }

        $renderInner = static fn(ReflectionType $innerType): string => self::renderTypeForPhpDoc($innerType, $imports);
        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map($renderInner, $type->getTypes()));
        }

        if ($type instanceof ReflectionIntersectionType) {
            return implode('&', array_map($renderInner, $type->getTypes()));
        }

        return 'mixed';
    }

    /**
     * Renders a class name for a phpDoc type: its alias in the imports, fully qualified without them
     */
    public static function renderClassNameForPhpDoc(string $className, ?ProxyImports $imports): string
    {
        return $imports !== null ? $imports->import($className) : '\\' . ltrim($className, '\\');
    }

    private static function buildNodeFromReflection(ReflectionType $type): Identifier|Name|NullableType|UnionType|IntersectionType
    {
        if ($type instanceof ReflectionNamedType) {
            $name = self::resolveReflectionNamedTypeName($type);

            return self::buildNamedTypeNode($name, $type->allowsNull() && $name !== 'null' && $name !== 'mixed');
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

    private static function buildNodeFromAst(Identifier|Name|ComplexType $node): Identifier|Name|NullableType|UnionType|IntersectionType
    {
        if ($node instanceof Identifier || $node instanceof Name) {
            return self::buildNamedOrClassNode(self::resolveAstTypeName($node));
        }

        if ($node instanceof NullableType) {
            $name = self::resolveAstTypeName($node->type);

            return self::buildNamedTypeNode($name, $name !== 'null' && $name !== 'mixed');
        }

        if ($node instanceof UnionType) {
            /** @var list<Identifier|IntersectionType|Name> $parts */
            $parts = [];
            foreach ($node->types as $innerType) {
                $innerNode = self::buildNodeFromAst($innerType);
                if ($innerNode instanceof Identifier || $innerNode instanceof Name || $innerNode instanceof IntersectionType) {
                    $parts[] = $innerNode;
                }
            }

            return new UnionType($parts);
        }

        if ($node instanceof IntersectionType) {
            /** @var list<Identifier|Name> $parts */
            $parts = [];
            foreach ($node->types as $innerType) {
                $parts[] = self::buildNamedOrClassNode(self::resolveAstTypeName($innerType));
            }

            return new IntersectionType($parts);
        }

        throw WeavingException::unsupportedTypeNode($node->getType());
    }

    /**
     * Returns the name of a named type node: the resolved class name when the NameResolver has set it
     */
    private static function resolveAstTypeName(Identifier|Name $node): string
    {
        if ($node instanceof Name && !$node instanceof Name\FullyQualified) {
            $resolvedName = $node->getAttribute('resolvedName');
            if ($resolvedName instanceof Name) {
                return $resolvedName->toString();
            }
        }

        return $node->toString();
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
                    throw WeavingException::malformedDnfType($typeStr);
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
        return in_array(strtolower($name), self::BUILTIN_TYPES, true)
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
