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

namespace Go\Instrument\Transformer\Weaving;

use Go\ParserReflection\ReflectionClass;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Property;
use ReflectionProperty;

/**
 * Removes the declarations of intercepted properties from the woven trait body
 *
 * The proxy re-declares these properties with native PHP 8.4 hooks. Tokens are blanked (not deleted) and every newline
 * is kept, so original line numbers survive for debugger mapping, whatever the declaration spans: multi-line defaults,
 * attributes on their own lines or several properties declared in one statement. A newline-free marker comment is left
 * in place of every removed item; never a `//` prefix, it would leave multi-line declarations live (issue #669).
 *
 * @internal
 */
final readonly class InterceptedPropertyRemover
{
    public function __construct(
        /** Demotes intercepted promoted properties, whose declarations can not be removed */
        private PromotedPropertyDemoter $promotedPropertyDemoter = new PromotedPropertyDemoter(),
    ) {}

    public function remove(ReflectionClass $class, InterceptedMembers $members, TokenStreamEditor $editor): void
    {
        if (!$members->hasProperties()) {
            return;
        }

        // Plain property declarations are taken from the AST of this class/trait, so only its
        // own declarations are touched and a grouped declaration is handled once, per item
        foreach ($class->getNode()->stmts as $statement) {
            if ($statement instanceof Property) {
                $this->removePropertyItems($class->name, $statement, $members, $editor);
            }
        }
        if ($class->isTrait()) {
            return;
        }

        $promotedParameters = $this->collectPromotedParameters($class, $members);
        if ($promotedParameters !== []) {
            $this->promotedPropertyDemoter->demote($class, $promotedParameters, $editor);
        }
    }

    /**
     * Returns the constructor parameters of the intercepted promoted properties declared by the class, by name
     *
     * @return array<string, Param>
     */
    private function collectPromotedParameters(ReflectionClass $class, InterceptedMembers $members): array
    {
        $mask       = ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE;
        $parameters = [];
        foreach ($class->getProperties($mask) as $property) {
            if (!$members->hasProperty($property->getName())) {
                continue;
            }
            if ($property->getDeclaringClass()->name !== $class->name || !method_exists($property, 'getTypeNode')) {
                continue;
            }
            $propertyNode = $property->getTypeNode();
            if ($propertyNode instanceof Param) {
                $parameters[$property->getName()] = $propertyNode;
            }
        }

        return $parameters;
    }

    /**
     * Removes the intercepted items of one property declaration statement
     *
     * When every item of the statement is intercepted, the whole statement (attributes, modifiers, type, items and
     * the semicolon) is blanked. Otherwise, only the intercepted items are blanked together with the commas that
     * separated them, so the remaining items keep their shared attributes, modifiers and type:
     * `public int $a = 1, $b;` with `$a` intercepted keeps `public int $b;` in the trait.
     */
    private function removePropertyItems(
        string $className,
        Property $statement,
        InterceptedMembers $members,
        TokenStreamEditor $editor,
    ): void {
        $items     = [];
        $isRemoved = [];
        $markers   = [];
        foreach ($statement->props as $index => $item) {
            $itemRange = TokenStreamEditor::rangeOf($item);
            if ($itemRange === null) {
                return;
            }
            $items[$index]     = $itemRange;
            $propertyName      = $item->name->name;
            $isRemoved[$index] = $members->hasProperty($propertyName);
            if ($isRemoved[$index]) {
                $markers[$index] = sprintf('/* Moved by weaving interceptor to the {@see %s->%s} */', $className, $propertyName);
            }
        }
        if ($markers === []) {
            return;
        }

        if (count($markers) === count($items)) {
            $statementRange = TokenStreamEditor::rangeOf($statement);
            if ($statementRange === null) {
                return;
            }
            [$start, $end] = $statementRange;
            $editor->blank($start, $end);
            $editor->prependToFirstToken($start, $end, implode(' ', $markers));

            return;
        }

        $lastIndex = array_key_last($items);
        foreach ($items as $index => [$start, $end]) {
            if ($isRemoved[$index]) {
                $editor->blank($start, $end);
                $editor->prependToFirstToken($start, $end, $markers[$index]);
            }
            if ($index === $lastIndex) {
                break;
            }
            // Exactly one comma separates two items; it survives only between two kept items:
            // it follows a kept item and another kept item comes later in the statement
            $keepsComma = !$isRemoved[$index] && in_array(false, array_slice($isRemoved, $index + 1), true);
            if ($keepsComma) {
                continue;
            }
            $commaPosition = $editor->find(',', $end + 1, $items[$index + 1][0] - 1);
            if ($commaPosition !== null) {
                $editor->replace($commaPosition, '');
            }
        }
    }
}
