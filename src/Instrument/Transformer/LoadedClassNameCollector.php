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

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

/**
 * Collects the names of the classes code loads when it runs: instantiations, static members, class constants (not
 * `X::class`) and the parents, interfaces and traits of the classes it declares. Bodies of functions, closures, arrow
 * functions and declared classes run later and are skipped; `instanceof`, type declarations and other references
 * that do not load a class are ignored.
 *
 * @internal
 */
final class LoadedClassNameCollector extends NodeVisitorAbstract
{
    /**
     * Fully qualified names of the loaded classes
     *
     * @var list<string>
     */
    public array $names = [];

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Stmt\Function_ || $node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction) {
            return NodeVisitor::DONT_TRAVERSE_CHILDREN;
        }
        if ($node instanceof Stmt\ClassLike) {
            array_push($this->names, ...self::getInheritedNames($node), ...self::getTraitUseNames($node));

            return NodeVisitor::DONT_TRAVERSE_CHILDREN;
        }
        $isLoadingReference = $node instanceof Expr\New_
            || $node instanceof Expr\StaticCall
            || $node instanceof Expr\StaticPropertyFetch
            || ($node instanceof Expr\ClassConstFetch
                && !($node->name instanceof Identifier && $node->name->toLowerString() === 'class'));
        if ($isLoadingReference && $node->class instanceof Name) {
            $this->names[] = self::resolveName($node->class);
        }

        return null;
    }

    /**
     * Names of the class-likes the declaration extends or implements
     *
     * @return list<string>
     */
    public static function getInheritedNames(Stmt\ClassLike $node): array
    {
        $names = match (true) {
            $node instanceof Stmt\Class_     => $node->extends !== null ? [$node->extends, ...$node->implements] : $node->implements,
            $node instanceof Stmt\Enum_      => $node->implements,
            $node instanceof Stmt\Interface_ => $node->extends,
            default                          => [],
        };

        return array_values(array_map(self::resolveName(...), $names));
    }

    /**
     * Names of the traits used by the declaration
     *
     * @return list<string>
     */
    public static function getTraitUseNames(Stmt\ClassLike $node): array
    {
        $names = [];
        foreach ($node->getTraitUses() as $traitUse) {
            foreach ($traitUse->traits as $trait) {
                $names[] = self::resolveName($trait);
            }
        }

        return $names;
    }

    /**
     * Fully qualified name of a class reference, resolved by the name resolver of the parser
     */
    public static function resolveName(Name $name): string
    {
        $resolvedName = $name->getAttribute('resolvedName');

        return $resolvedName instanceof Name ? $resolvedName->toString() : $name->toString();
    }
}
