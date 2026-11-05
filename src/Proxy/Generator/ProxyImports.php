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

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Name\Relative;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use ReflectionClass;

/**
 * Manages the `use` imports of one generated proxy file.
 *
 * Generated code references classes through short, readable aliases: framework and aspect classes
 * (`Interceptor::before(The::aspect(LoggingAspect::class)->...)`) as well as the classes in copied
 * signatures, attributes and default values (`function priceFor(Product $product): Money`). A short name
 * is only usable when nothing else in the file binds it: the imports copied from the original file, the
 * class itself, names used by the original class body (parameter defaults, attributes, types resolve
 * against the same file scope) or another imported class. On a collision this registry picks a
 * distinct alias automatically: `Aop<ShortName>` for framework classes and
 * `<NamespaceSegment><ShortName>` for other classes, with a numeric suffix as the last resort.
 *
 * @internal
 */
final class ProxyImports
{
    /**
     * Short names that are already bound in the generated file, lowercased (class names are case-insensitive):
     * alias => lowercased class or namespace name it stands for, '' when that is unknown
     *
     * @var array<string, string>
     */
    private array $boundNames = [];

    /**
     * Imports of the original file: class name => alias
     *
     * @var array<string, string>
     */
    private array $originalImports = [];

    /**
     * Classes imported by the original file: lowercased class name => true
     *
     * @var array<string, true>
     */
    private array $originalClassNames = [];

    /**
     * Imports added for generated code, in registration order: class name => alias
     *
     * @var array<string, string>
     */
    private array $generatedImports = [];

    /**
     * Alias of every class imported so far, the original imports included: lowercased class name => alias
     *
     * @var array<string, string>
     */
    private array $aliases = [];

    /**
     * Deep-clones copied nodes, referencing the classes in them through these imports
     */
    private ?NodeTraverser $nameImporter = null;

    /**
     * @param array<string, string|null> $originalImports Imports of the original file: class name => alias
     */
    public function __construct(
        /** Namespace of the generated file, empty for the global one */
        private readonly string $namespace = '',
        array $originalImports = [],
    ) {
        foreach ($originalImports as $className => $alias) {
            $className = ltrim($className, '\\');
            $alias     = ($alias === null || $alias === '') ? self::shortName($className) : $alias;

            $this->originalImports[$className]                = $alias;
            $this->originalClassNames[strtolower($className)] = true;
            $this->aliases[strtolower($className)]          ??= $alias;
            $this->bind($alias, $className);
        }
    }

    /**
     * Creates imports for a proxy of the given class: reserves its imports, its own short name and the
     * names used by its body (when the reflection exposes the AST, as goaop/parser-reflection does)
     *
     * @param ReflectionClass<covariant object> $class
     * @param array<string, string|null>        $originalImports Imports of the original file: class name => alias
     */
    public static function forClass(ReflectionClass $class, array $originalImports = []): self
    {
        $imports = new self($class->getNamespaceName(), $originalImports);
        $imports->bind($class->getShortName(), $class->getName());
        if (method_exists($class, 'getNode')) {
            $classNode = $class->getNode();
            if ($classNode instanceof Node) {
                $imports->reserveNamesUsedIn($classNode);
            }
        }

        return $imports;
    }

    /**
     * Marks a short name as bound in the generated file to something no generated reference may use
     */
    public function reserve(string $shortName): void
    {
        $this->bind($shortName, '');
    }

    /**
     * Reserves the first segment of every class or function name that is resolved against the file scope
     *
     * A name resolved by the NameResolver (`resolvedName` attribute) binds its first segment to the class or
     * namespace it stands for: generated code may reference that very class by the same short name.
     */
    public function reserveNamesUsedIn(Node ...$nodes): void
    {
        foreach (new NodeFinder()->findInstanceOf($nodes, Name::class) as $name) {
            if ($name instanceof FullyQualified || $name instanceof Relative || $name->isSpecialClassName()) {
                continue;
            }
            $resolvedName = $name->getAttribute('resolvedName');
            $target       = '';
            if ($resolvedName instanceof Name) {
                $resolvedParts = $resolvedName->getParts();
                $boundLength   = count($resolvedParts) - count($name->getParts()) + 1;
                $target        = implode('\\', array_slice($resolvedParts, 0, $boundLength));
            }
            $this->bind($name->getFirst(), $target);
        }
    }

    /**
     * Imports the class and returns the name generated code must use for it
     *
     * A class of the file namespace whose short name is free is referenced by that name without an import.
     */
    public function import(string $className): string
    {
        $className = ltrim($className, '\\');
        $key       = strtolower($className);
        if (isset($this->aliases[$key])) {
            return $this->generatedImports[$className] ??= $this->aliases[$key];
        }

        $shortName = self::shortName($className);
        // A global class needs no import in a file without namespace
        if ($this->namespace === '' && $shortName === $className) {
            return $this->aliases[$key] = $this->generatedImports[$className] = $className;
        }

        $alias = $shortName;
        if (!$this->isAliasFreeFor($alias, $key)) {
            $prefixed = self::aliasPrefix($className) . $shortName;
            $alias    = $prefixed;
            for ($counter = 2; !$this->isAliasFreeFor($alias, $key); ++$counter) {
                $alias = $prefixed . $counter;
            }
        }
        $this->bind($alias, $className);

        return $this->aliases[$key] = $this->generatedImports[$className] = $alias;
    }

    /**
     * Deep-clones copied nodes (default values, attribute arguments) for the generated file
     *
     * Every name resolved by the NameResolver keeps its meaning: class references (`Foo::BAR`, `new Foo()`,
     * `Foo::class`, types inside closures) use the alias of their import, function and constant names are
     * fully qualified. Unresolved names (e.g. unqualified global constants like PHP_INT_MAX with namespace
     * fallback semantics) and the `self`/`parent`/`static` keywords are kept as written.
     *
     * @template T of Node
     *
     * @param array<T> $nodes
     *
     * @return list<T>
     */
    public function importNamesIn(array $nodes): array
    {
        $this->nameImporter ??= new NodeTraverser(new CloningVisitor(), new ImportedNameVisitor($this));

        /** @var list<T> */
        return array_values($this->nameImporter->traverse($nodes));
    }

    /**
     * Returns the imports to declare in the generated file: class name => alias, or null when the alias
     * is the short name. Imports for generated code come first, followed by the original imports.
     *
     * @return array<string, string|null>
     */
    public function getUses(): array
    {
        $uses = [];
        foreach ($this->generatedImports as $className => $alias) {
            if (isset($this->originalClassNames[strtolower($className)])) {
                continue;
            }
            $shortName = self::shortName($className);
            // Global classes in a file without namespace and classes of the file namespace under their own
            // short name resolve without an import
            if ($alias === $shortName && strcasecmp(self::namespaceName($className), $this->namespace) === 0) {
                continue;
            }
            $uses[$className] = $alias === $shortName ? null : $alias;
        }
        foreach ($this->originalImports as $className => $alias) {
            $uses[$className] = $alias;
        }

        return $uses;
    }

    /**
     * Binds a short name to the class or namespace it stands for, '' when unknown; conflicting bindings are unknown
     */
    private function bind(string $alias, string $target): void
    {
        $alias  = strtolower($alias);
        $target = strtolower(ltrim($target, '\\'));
        $bound  = $this->boundNames[$alias] ?? null;

        $this->boundNames[$alias] = ($bound === null || $bound === $target) ? $target : '';
    }

    /**
     * An alias can reference the class when it is not bound yet or already stands for the class itself
     */
    private function isAliasFreeFor(string $alias, string $lowerClassName): bool
    {
        $bound = $this->boundNames[strtolower($alias)] ?? null;

        return $bound === null || $bound === $lowerClassName;
    }

    private static function shortName(string $className): string
    {
        $lastSeparator = strrpos($className, '\\');

        return $lastSeparator === false ? $className : substr($className, $lastSeparator + 1);
    }

    private static function namespaceName(string $className): string
    {
        $lastSeparator = strrpos($className, '\\');

        return $lastSeparator === false ? '' : substr($className, 0, $lastSeparator);
    }

    /**
     * Framework classes get the `Aop` prefix, other classes the namespace segment above their short name
     */
    private static function aliasPrefix(string $className): string
    {
        if (str_starts_with($className, 'Go\\Aop\\')) {
            return 'Aop';
        }
        $segments = explode('\\', $className);

        return count($segments) > 1 ? $segments[count($segments) - 2] : 'Global';
    }
}
