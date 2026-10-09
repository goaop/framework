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

namespace Go\Instrument\Transformer;

use Go\Aop\Exception\WeavingException;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;

/**
 * Places the proxies of a source file declaring several woven class-likes: the order of the proxies in the proxy file
 * and the declaration after which the woven source includes that file.
 *
 * A proxy always uses its `…OriginalTrait`, so PHP declares it at runtime, when the include runs. A woven trait without
 * trait uses is declared at compile time, before any code of the file runs; one with trait uses is declared at runtime,
 * at its position. The include goes after the last woven class-like whose proxy depends on such a position (its trait
 * uses something, or it extends/implements a class-like of the file that is not woven), else after the first one.
 *
 * Code that runs between the first woven class-like and that point can not use the woven class-likes before it, as it
 * could with one proxy per class: the file is rejected with a WeavingException instead of failing with "Class not found"
 * while it is loaded. Detected:
 *  - a class-like declared up to the include point that needs a woven class-like of the file: an unwoven one extending,
 *    implementing or using it, a woven one using it (its trait uses stay in its `…OriginalTrait`);
 *  - a top-level statement between the first woven class-like and the include point that loads a woven class-like
 *    declared before it: `new X`, `X::method()`, `X::$property`, `X::CONSTANT` (not `X::class`) or a class declared
 *    by it extending/implementing/using X. Bodies of closures, arrow functions and functions are not looked into,
 *    `instanceof`, type declarations and other references that do not load a class are ignored.
 *
 * @internal
 */
final class ProxyIncludePlanner
{
    /**
     * Sorts the proxies for the proxy file: source order, a proxy extending another proxy of the file comes after it
     *
     * @param non-empty-list<WovenProxy> $proxies Proxies in source order
     *
     * @return non-empty-list<WovenProxy>
     */
    public static function sortForProxyFile(array $proxies): array
    {
        $byName = [];
        foreach ($proxies as $proxy) {
            $byName[strtolower($proxy->class->getName())] = $proxy;
        }
        $placed = [];
        $sorted = [];
        foreach ($proxies as $proxy) {
            self::place($proxy, $byName, $placed, $sorted);
        }
        assert($sorted !== []);

        return $sorted;
    }

    /**
     * Returns the proxy after whose declaration the proxy file is included
     *
     * @param array<Node>                $syntaxTree Syntax tree of the source file
     * @param non-empty-list<WovenProxy> $proxies    Proxies in source order
     * @param string                     $fileName   Source file name, for the error message
     *
     * @throws WeavingException When code running before the include point needs a woven class-like
     */
    public static function findIncludePoint(array $syntaxTree, array $proxies, string $fileName): WovenProxy
    {
        $includePoint = $proxies[0];
        if (count($proxies) === 1) {
            return $includePoint;
        }

        $statements = self::getTopLevelStatements($syntaxTree);
        $wovenNames = [];
        $wovenNodes = [];
        foreach ($proxies as $proxy) {
            $wovenNames[strtolower($proxy->class->getName())] = $proxy;
            $wovenNodes[spl_object_id($proxy->node)] = true;
        }
        $unwovenNames = [];
        foreach ($statements as $statement) {
            if ($statement instanceof Stmt\ClassLike && $statement->namespacedName !== null) {
                $name = strtolower($statement->namespacedName->toString());
                if (!isset($wovenNames[$name])) {
                    $unwovenNames[$name] = true;
                }
            }
        }
        foreach ($proxies as $proxy) {
            if (self::isPositionBound($proxy->node, $unwovenNames)) {
                $includePoint = $proxy;
            }
        }

        $problems = self::findLoadTimeDependencies($statements, $proxies, $wovenNames, $wovenNodes, $includePoint);
        if ($problems !== []) {
            $wovenClassNames = array_map(static fn(WovenProxy $proxy): string => $proxy->class->getName(), $proxies);

            throw new WeavingException(sprintf(
                'Can not weave %s: the proxies of its woven class-likes %s are declared by one proxy file, included '
                . 'after %s (line %d), the first point where all of them can be declared. Code running before that '
                . 'point needs a woven class-like: %s. Move that code or the woven class-likes so that the code runs '
                . 'after %s, split the file into one class per file, or exclude the class-likes from the pointcut.',
                $fileName,
                implode(', ', $wovenClassNames),
                $includePoint->class->getName(),
                $includePoint->node->getEndLine(),
                implode('; ', $problems),
                $includePoint->class->getName(),
            ));
        }

        return $includePoint;
    }

    /**
     * @param list<Stmt>                 $statements   Top-level statements of the file
     * @param non-empty-list<WovenProxy> $proxies      Proxies in source order
     * @param array<string, WovenProxy>  $wovenNames   Woven class-likes by lowercase name
     * @param array<int, true>           $wovenNodes   Object ids of the woven declarations
     *
     * @return list<string> Descriptions of the code needing a woven class-like before the include point
     */
    private static function findLoadTimeDependencies(
        array $statements,
        array $proxies,
        array $wovenNames,
        array $wovenNodes,
        WovenProxy $includePoint,
    ): array {
        $problems      = [];
        $firstWovenEnd = $proxies[0]->endTokenPosition;
        foreach ($statements as $statement) {
            if ($statement->getEndTokenPos() > $includePoint->endTokenPosition) {
                continue;
            }
            if ($statement instanceof Stmt\ClassLike) {
                // Extends/implements of a woven class-like move to its proxy, its trait uses stay in the woven trait
                $neededNames = LoadedClassNameCollector::getTraitUseNames($statement);
                if (!isset($wovenNodes[spl_object_id($statement)])) {
                    $neededNames = [...LoadedClassNameCollector::getInheritedNames($statement), ...$neededNames];
                }
                foreach ($neededNames as $neededName) {
                    if (isset($wovenNames[strtolower($neededName)])) {
                        $problems[] = sprintf(
                            '%s (line %d) needs %s',
                            $statement->namespacedName?->toString() ?? 'class',
                            $statement->getStartLine(),
                            $wovenNames[strtolower($neededName)]->class->getName(),
                        );
                    }
                }
                continue;
            }
            $statementStart = $statement->getStartTokenPos();
            if ($statementStart <= $firstWovenEnd || self::isDeclarationOnly($statement)) {
                continue;
            }
            $collector = new LoadedClassNameCollector();
            $traverser = new NodeTraverser($collector);
            $traverser->traverse([$statement]);
            foreach ($collector->names as $reference) {
                $woven = $wovenNames[strtolower($reference)] ?? null;
                if ($woven !== null && $woven->endTokenPosition < $statementStart) {
                    $problems[] = sprintf(
                        'the statement on line %d uses %s',
                        $statement->getStartLine(),
                        $woven->class->getName(),
                    );
                }
            }
        }

        return array_values(array_unique($problems));
    }

    /**
     * A proxy depends on the position of its declaration when its woven trait keeps trait uses (PHP declares such a
     * trait at runtime) or when it extends/implements a class-like of the file that is not woven
     *
     * @param array<string, true> $unwovenNames Lowercase names of the class-likes of the file that are not woven
     */
    private static function isPositionBound(Stmt\ClassLike $node, array $unwovenNames): bool
    {
        if ($node->getTraitUses() !== []) {
            return true;
        }
        foreach (LoadedClassNameCollector::getInheritedNames($node) as $inheritedName) {
            if (isset($unwovenNames[strtolower($inheritedName)])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, WovenProxy> $byName Proxies by lowercase class name
     * @param array<string, true>       $placed Lowercase names of the visited proxies
     * @param list<WovenProxy>          $sorted
     */
    private static function place(WovenProxy $proxy, array $byName, array &$placed, array &$sorted): void
    {
        $key = strtolower($proxy->class->getName());
        if (isset($placed[$key])) {
            return;
        }
        $placed[$key] = true;
        if ($proxy->node instanceof Stmt\Class_ && $proxy->node->extends !== null) {
            $parent = $byName[strtolower(LoadedClassNameCollector::resolveName($proxy->node->extends))] ?? null;
            if ($parent !== null) {
                self::place($parent, $byName, $placed, $sorted);
            }
        }
        $sorted[] = $proxy;
    }

    /**
     * Statements directly in the file or in its namespace blocks
     *
     * @param array<Node> $syntaxTree
     *
     * @return list<Stmt>
     */
    private static function getTopLevelStatements(array $syntaxTree): array
    {
        $statements = [];
        foreach ($syntaxTree as $node) {
            if ($node instanceof Stmt\Namespace_) {
                array_push($statements, ...$node->stmts);
            } elseif ($node instanceof Stmt\Declare_) {
                array_push($statements, ...($node->stmts ?? []));
            } elseif ($node instanceof Stmt) {
                $statements[] = $node;
            }
        }

        return $statements;
    }

    /**
     * Statements that run no code while the file is loaded
     */
    private static function isDeclarationOnly(Stmt $statement): bool
    {
        return $statement instanceof Stmt\Function_
            || $statement instanceof Stmt\Use_
            || $statement instanceof Stmt\GroupUse
            || $statement instanceof Stmt\Nop
            || $statement instanceof Stmt\InlineHTML
            || $statement instanceof Stmt\HaltCompiler
            || $statement instanceof Stmt\Declare_;
    }
}
