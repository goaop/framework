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
use ReflectionClass;

/**
 * Manages the `use` imports of one generated proxy file.
 *
 * Generated code references framework and aspect classes through short, readable aliases
 * (`Interceptor::before(The::aspect(LoggingAspect::class)->...)`). A short name is only usable
 * when nothing else in the file binds it: the imports copied from the original file, the class
 * itself, names used by the original class body (parameter defaults, attributes, types resolve
 * against the same file scope) or another imported class. On a collision this registry picks a
 * distinct alias automatically: `Aop<ShortName>` for framework classes and
 * `<NamespaceSegment><ShortName>` for other classes, with a numeric suffix as the last resort.
 *
 * @internal
 */
final class ProxyImports
{
    /**
     * Names that are already bound in the generated file, lowercased (class names are case-insensitive)
     *
     * @var array<string, true>
     */
    private array $reservedNames = [];

    /**
     * Imports of the original file: class name => alias
     *
     * @var array<string, string>
     */
    private array $originalImports = [];

    /**
     * Imports added for generated code, in registration order: class name => alias
     *
     * @var array<string, string>
     */
    private array $generatedImports = [];

    /**
     * @param string                     $namespace       Namespace of the generated file, empty for the global one
     * @param array<string, string|null> $originalImports Imports of the original file: class name => alias
     */
    public function __construct(private readonly string $namespace = '', array $originalImports = [])
    {
        foreach ($originalImports as $className => $alias) {
            $className = ltrim($className, '\\');
            $alias     = ($alias === null || $alias === '') ? self::shortName($className) : $alias;

            $this->originalImports[$className] = $alias;
            $this->reserve($alias);
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
        $imports->reserve($class->getShortName());
        if (method_exists($class, 'getNode')) {
            $classNode = $class->getNode();
            if ($classNode instanceof Node) {
                $imports->reserveNamesUsedIn($classNode);
            }
        }

        return $imports;
    }

    /**
     * Marks a short name as bound in the generated file
     */
    public function reserve(string $shortName): void
    {
        $this->reservedNames[strtolower($shortName)] = true;
    }

    /**
     * Reserves the first segment of every class or function name that is resolved against the file scope
     */
    public function reserveNamesUsedIn(Node ...$nodes): void
    {
        foreach (new NodeFinder()->findInstanceOf($nodes, Name::class) as $name) {
            if ($name instanceof FullyQualified || $name instanceof Relative || $name->isSpecialClassName()) {
                continue;
            }
            $this->reserve($name->getFirst());
        }
    }

    /**
     * Imports the class and returns the name generated code must use for it
     */
    public function import(string $className): string
    {
        $className = ltrim($className, '\\');
        if (isset($this->generatedImports[$className])) {
            return $this->generatedImports[$className];
        }
        // The original file already imports this class: reuse its alias
        if (isset($this->originalImports[$className])) {
            return $this->generatedImports[$className] = $this->originalImports[$className];
        }

        $shortName = self::shortName($className);
        // A global class needs no import in a file without namespace
        if ($this->namespace === '' && $shortName === $className) {
            return $className;
        }

        $alias = $shortName;
        if (isset($this->reservedNames[strtolower($alias)])) {
            $prefixed = self::aliasPrefix($className) . $shortName;
            $alias    = $prefixed;
            for ($counter = 2; isset($this->reservedNames[strtolower($alias)]); ++$counter) {
                $alias = $prefixed . $counter;
            }
        }
        $this->reserve($alias);

        return $this->generatedImports[$className] = $alias;
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
            if (isset($this->originalImports[$className])) {
                continue;
            }
            if ($this->namespace === '' && !str_contains($className, '\\')) {
                continue;
            }
            $uses[$className] = $alias === self::shortName($className) ? null : $alias;
        }
        foreach ($this->originalImports as $className => $alias) {
            $uses[$className] = $alias;
        }

        return $uses;
    }

    private static function shortName(string $className): string
    {
        $lastSeparator = strrpos($className, '\\');

        return $lastSeparator === false ? $className : substr($className, $lastSeparator + 1);
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
