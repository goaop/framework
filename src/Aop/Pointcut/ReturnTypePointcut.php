<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2017, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Go\Aop\Pointcut;
use Go\ParserReflection\ReflectionFileNamespace;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Return type filter that matches methods and functions with a specific return type.
 *
 * Type name can contain wildcards '*' and '**' (each applied per type member).
 *
 * Union, intersection and DNF types are supported with the following semantics — both the
 * pattern and the actual return type are normalized into sets of intersection groups (the
 * declaration is split on '|' at parenthesis depth zero, each resulting member on '&';
 * parentheses and leading backslashes are normalized away; a leading '?' nullable marker is
 * expanded on both sides, '?Foo' being equivalent to 'Foo|null'):
 *
 *  - A single-type pattern (no '|' and no '&', e.g. 'string' or 'Some*Interface') matches if
 *    ANY member of the actual type matches it. For example, the pattern 'string' matches
 *    methods returning 'string', 'string|int', '?string' and 'string|null'.
 *  - A composite pattern (union and/or intersection, e.g. 'string|int' or 'Countable&Iterator')
 *    matches only when the pattern's member set corresponds one-to-one to the actual type's
 *    member set, regardless of the order of members ('int|string' matches 'string|int').
 *    Each pattern member may still use wildcards ('Some*|null' matches 'SomeClass|null').
 */
final readonly class ReturnTypePointcut implements Pointcut
{
    /**
     * Trimmed constructor pattern, retained verbatim for re-emission into compiled advisor caches.
     */
    private string $returnTypeName; // @phpstan-ignore property.onlyWritten (consumed by the upcoming advisor cache compilation)

    /**
     * Normalized pattern: list of intersection groups, each group is a list of atomic patterns.
     *
     * @var list<list<string>>
     */
    private array $patternGroups;

    /**
     * Whether the pattern consists of one single atomic type (no union/intersection).
     */
    private bool $isSingleAtomicPattern;

    /**
     * Return type name matcher constructor accepts name or glob pattern of the type to match.
     *
     * The pattern may be a plain type name ('string'), contain wildcards ('Some*'), or be a
     * union/intersection/DNF type declaration ('string|int', 'Countable&Iterator',
     * '(Countable&Iterator)|null').
     */
    public function __construct(string $returnTypeName)
    {
        $returnTypeName = trim($returnTypeName, " \t\\");
        if (strlen($returnTypeName) === 0) {
            throw new InvalidArgumentException("Return type name must not be empty");
        }
        $this->returnTypeName        = $returnTypeName;
        $this->patternGroups         = self::normalizeTypeExpression($returnTypeName);
        $this->isSingleAtomicPattern = count($this->patternGroups) === 1 && count($this->patternGroups[0]) === 1;
    }

    public function matches(
        ReflectionClass|ReflectionFileNamespace                $context,
        ReflectionMethod|ReflectionProperty|ReflectionFunction|null $reflector = null,
    ): bool {
        // With only static context we always match, as we don't have any information about concrete reflector
        if (!isset($reflector)) {
            return true;
        }

        // We don't support anything that is not function-like
        if (!$reflector instanceof ReflectionFunctionAbstract) {
            return false;
        }

        // If reflector doesn't have a return type, we should not match
        if (!$reflector->hasReturnType()) {
            return false;
        }

        $actualGroups = self::normalizeTypeExpression((string) $reflector->getReturnType());

        // Single-type pattern: match if any member of the actual type matches
        if ($this->isSingleAtomicPattern) {
            $atomicPattern = $this->patternGroups[0][0];
            foreach ($actualGroups as $actualGroup) {
                foreach ($actualGroup as $actualAtom) {
                    if (self::atomMatches($atomicPattern, $actualAtom)) {
                        return true;
                    }
                }
            }

            return false;
        }

        // Composite pattern: pattern member set must correspond one-to-one to the actual member set
        return self::matchOneToOne(
            $this->patternGroups,
            $actualGroups,
            static fn(array $patternGroup, array $actualGroup): bool => self::matchOneToOne(
                $patternGroup,
                $actualGroup,
                static fn(string $patternAtom, string $actualAtom): bool => self::atomMatches($patternAtom, $actualAtom),
            ),
        );
    }

    public function getKind(): int
    {
        return Pointcut::KIND_METHOD | Pointcut::KIND_FUNCTION;
    }

    /**
     * Normalizes a type declaration into a sorted set of intersection groups.
     *
     * A leading '?' nullable marker is expanded, '?Foo' being normalized into 'Foo|null'.
     * Parentheses around DNF groups are removed, and each atomic type is trimmed
     * from whitespace and leading backslashes.
     *
     * @return list<list<string>> List of intersection groups, each a sorted list of atomic types
     */
    private static function normalizeTypeExpression(string $type): array
    {
        $type = trim($type);
        if (str_starts_with($type, '?')) {
            $type = substr($type, 1) . '|null';
        }

        $groups = [];
        foreach (self::splitAtDepthZero($type) as $member) {
            $member = trim($member);
            if (str_starts_with($member, '(') && str_ends_with($member, ')')) {
                $member = substr($member, 1, -1);
            }
            $atoms = [];
            foreach (explode('&', $member) as $atom) {
                $atom = ltrim(trim($atom), '\\');
                if ($atom !== '') {
                    $atoms[] = $atom;
                }
            }
            if ($atoms !== []) {
                sort($atoms, SORT_STRING);
                $groups[] = $atoms;
            }
        }
        usort($groups, static fn(array $left, array $right): int => implode('&', $left) <=> implode('&', $right));

        return $groups;
    }

    /**
     * Splits a type declaration on '|' at parenthesis depth zero.
     *
     * @return list<string>
     */
    private static function splitAtDepthZero(string $type): array
    {
        $members = [];
        $current = '';
        $depth   = 0;
        foreach (str_split($type) as $char) {
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            } elseif ($char === '|' && $depth === 0) {
                $members[] = $current;
                $current   = '';
                continue;
            }
            $current .= $char;
        }
        $members[] = $current;

        return $members;
    }

    /**
     * Checks whether one atomic type pattern (with a possible '*' wildcard) matches an atomic type.
     */
    private static function atomMatches(string $pattern, string $actual): bool
    {
        if ($pattern === $actual) {
            return true;
        }
        $regexp = '/^(' . strtr(preg_quote($pattern, '/'), [
            '\\*' => '[^\\\\]+',
        ]) . ')$/';

        return (bool) preg_match($regexp, $actual);
    }

    /**
     * Checks whether pattern items can be matched one-to-one (bijectively) onto actual items.
     *
     * Uses simple backtracking; item counts in real-world type declarations are tiny.
     *
     * @template TPattern
     * @template TActual
     * @param list<TPattern>                          $patternItems
     * @param list<TActual>                           $actualItems
     * @param callable(TPattern, TActual): bool       $matcher
     */
    private static function matchOneToOne(array $patternItems, array $actualItems, callable $matcher): bool
    {
        if (count($patternItems) !== count($actualItems)) {
            return false;
        }
        if ($patternItems === []) {
            return true;
        }
        $patternItem = array_shift($patternItems);
        foreach ($actualItems as $index => $actualItem) {
            if ($matcher($patternItem, $actualItem)) {
                $remaining = $actualItems;
                unset($remaining[$index]);
                if (self::matchOneToOne($patternItems, array_values($remaining), $matcher)) {
                    return true;
                }
            }
        }

        return false;
    }
}
