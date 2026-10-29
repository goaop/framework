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
use Go\ParserReflection\ReflectionMethod;
use PhpParser\Node\Param;

/**
 * Turns intercepted promoted constructor properties into plain constructor parameters (issue #599)
 *
 * The declaration of a promoted property can not be removed from the woven trait, it doubles as the constructor
 * parameter. Its promotion modifiers are removed instead, the proxy re-declares the property with interception hooks,
 * and the constructor body starts with an assignment to it, which routes the write through the set hook of the proxy.
 *
 * @internal
 */
final class PromotedPropertyDemoter
{
    /**
     * Modifiers that make a constructor parameter a promoted property
     */
    private const array PROMOTION_MODIFIERS = [
        T_PUBLIC, T_PROTECTED, T_PRIVATE,
        T_PUBLIC_SET, T_PROTECTED_SET, T_PRIVATE_SET,
        T_READONLY, T_FINAL,
    ];

    /**
     * @param non-empty-array<string, Param> $parameters Constructor parameters of the intercepted properties by name
     */
    public function demote(ReflectionClass $class, array $parameters, TokenStreamEditor $editor): void
    {
        $assignments = [];
        foreach ($parameters as $propertyName => $parameter) {
            $this->removePromotionModifiers($parameter, $editor);
            $assignments[] = sprintf('$this->%1$s = $%1$s;', $propertyName);
        }
        $this->injectConstructorAssignments($class, $assignments, $editor);
    }

    /**
     * Removes the promotion modifiers (visibility, asymmetric set-visibility, readonly, final) of the parameter
     *
     * Attributes, type, name and default value are kept, so is the whitespace holding newlines (line numbers).
     */
    private function removePromotionModifiers(Param $parameter, TokenStreamEditor $editor): void
    {
        $range = TokenStreamEditor::rangeOf($parameter);
        if ($range === null) {
            return;
        }
        [$position, $end] = $range;
        while ($position <= $end) {
            $token = $editor->token($position);
            if ($token === null) {
                ++$position;
                continue;
            }
            // Modifiers can only appear before the parameter variable — stop there so that
            // tokens inside the default value expression are never touched
            if ($token->id === T_VARIABLE) {
                break;
            }
            if ($token->id === T_ATTRIBUTE) {
                $position = $editor->skipAttributeGroup($position, $end);
                continue;
            }
            if (in_array($token->id, self::PROMOTION_MODIFIERS, true)) {
                $editor->removeWithInlineWhitespace($position);
            }
            ++$position;
        }
    }

    /**
     * Injects property assignments at the very beginning of the constructor body
     *
     * The assignments are appended to the opening '{' token of the constructor body, all on the same line, so the
     * original line numbers of the constructor statements are preserved.
     *
     * @param non-empty-list<string> $assignments Assignment statements like '$this->name = $name;'
     */
    private function injectConstructorAssignments(
        ReflectionClass $class,
        array $assignments,
        TokenStreamEditor $editor,
    ): void {
        $constructor = $class->getConstructor();
        if (!$constructor instanceof ReflectionMethod) {
            return;
        }
        $range = TokenStreamEditor::rangeOf($constructor->getNode());
        if ($range === null) {
            return;
        }
        $bodyPosition = $this->findBodyOpeningBrace($editor, ...$range);
        if ($bodyPosition !== null) {
            $editor->append($bodyPosition, ' ' . implode(' ', $assignments));
        }
    }

    /**
     * Returns the position of the '{' opening the body of the function declared in [$start, $end]
     *
     * The body '{' is the first '{' token after the parameter list closes (parenthesis depth back to zero). Hook
     * bodies of promoted parameters contain '{' too, but they are always nested inside the parameter parentheses,
     * so the depth guard skips them. Attribute groups before the 'function' keyword are skipped, their arguments
     * may contain arbitrary parentheses.
     */
    private function findBodyOpeningBrace(TokenStreamEditor $editor, int $start, int $end): ?int
    {
        $position          = $start;
        $seenFunction      = false;
        $seenParameterList = false;
        $parenthesisDepth  = 0;
        while ($position <= $end) {
            $token = $editor->token($position);
            if ($token === null) {
                ++$position;
                continue;
            }
            if (!$seenFunction) {
                if ($token->id === T_ATTRIBUTE) {
                    $position = $editor->skipAttributeGroup($position, $end);
                    continue;
                }
                $seenFunction = ($token->id === T_FUNCTION);
                ++$position;
                continue;
            }
            if ($token->text === '(') {
                ++$parenthesisDepth;
                $seenParameterList = true;
            } elseif ($token->text === ')') {
                --$parenthesisDepth;
            } elseif ($token->text === '{' && $seenParameterList && $parenthesisDepth === 0) {
                return $position;
            }
            ++$position;
        }

        return null;
    }
}
