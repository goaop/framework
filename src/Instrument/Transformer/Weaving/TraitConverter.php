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

use Go\Aop\Exception\WeavingException;
use Go\ParserReflection\ReflectionClass;
use PhpParser\Node\Attribute;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\EnumCase;
use ReflectionMethod;

/**
 * Converts the source of a woven class, enum or trait into the trait the proxy uses
 *
 * The woven trait keeps the original method bodies on their original lines (XDebug breakpoint mapping), the proxy
 * generated next to it re-declares what a trait can not hold: parent, interfaces, enum cases, intercepted properties
 * and the class attributes that are invalid on traits.
 *
 * - Class: `class` becomes `trait`, modifiers and the `extends`/`implements` clauses are removed.
 * - Enum: `enum` becomes `trait`, the backed type, the `implements` clause and every case are removed.
 * - Trait: only renamed, TraitProxyGenerator generates a child trait using it.
 *
 * @internal
 */
final readonly class TraitConverter
{
    /**
     * Class modifiers a trait declaration can not have
     */
    private const array CLASS_MODIFIERS = [T_FINAL, T_ABSTRACT, T_READONLY];

    /**
     * Class-level attributes that are compile-time invalid on traits
     *
     * PHP raises "Cannot apply #[\Attribute] to trait" (and the same for #[\AllowDynamicProperties]) at load time.
     * The proxy class re-declares them from the AST via AttributeGroupsGenerator, so attribute classes keep working
     * (issue #615).
     */
    private const array TRAIT_INCOMPATIBLE_ATTRIBUTES = ['Attribute', 'AllowDynamicProperties'];

    public function __construct(
        /** Keeps #[\Override] away from the trait aliases of intercepted methods */
        private OverrideAttributeStripper $overrideAttributeStripper = new OverrideAttributeStripper(),
        /** Removes the declarations the proxy re-declares with interception hooks */
        private InterceptedPropertyRemover $interceptedPropertyRemover = new InterceptedPropertyRemover(),
    ) {}

    /**
     * Converts the declaration of the class-like into the trait $traitName, in the token stream of its file
     *
     * @param array<string, ReflectionMethod> $methods Methods of the class by name
     *
     * @throws WeavingException When an intercepted method can not be aliased, or the declaration has no tokens
     */
    public function convert(
        ReflectionClass $class,
        InterceptedMembers $members,
        array $methods,
        TokenStreamEditor $editor,
        string $traitName,
    ): void {
        $this->overrideAttributeStripper->assertStrippable($class, $members, $methods);

        if ($class->isTrait()) {
            $this->convertTrait($class, $members, $methods, $editor, $traitName);
        } elseif ($class->isEnum()) {
            $this->convertEnum($class, $members, $methods, $editor, $traitName);
        } else {
            $this->convertClass($class, $members, $methods, $editor, $traitName);
        }
    }

    /**
     * @param array<string, ReflectionMethod> $methods Methods of the trait by name
     */
    private function convertTrait(
        ReflectionClass $class,
        InterceptedMembers $members,
        array $methods,
        TokenStreamEditor $editor,
        string $traitName,
    ): void {
        $this->interceptedPropertyRemover->remove($class, $members, $editor);
        $this->rewriteHeader($class->getNode(), $editor, $traitName, false);
        $this->overrideAttributeStripper->strip($class, $members, $methods, $editor);
    }

    /**
     * @param array<string, ReflectionMethod> $methods Methods of the enum by name
     */
    private function convertEnum(
        ReflectionClass $class,
        InterceptedMembers $members,
        array $methods,
        TokenStreamEditor $editor,
        string $traitName,
    ): void {
        $this->rewriteHeader($class->getNode(), $editor, $traitName, true);
        $this->removeEnumCases($class->getNode(), $editor);
        $this->overrideAttributeStripper->strip($class, $members, $methods, $editor);
    }

    /**
     * @param array<string, ReflectionMethod> $methods Methods of the class by name
     */
    private function convertClass(
        ReflectionClass $class,
        InterceptedMembers $members,
        array $methods,
        TokenStreamEditor $editor,
        string $traitName,
    ): void {
        $classNode = $class->getNode();
        $this->rewriteHeader($classNode, $editor, $traitName, true);
        $this->interceptedPropertyRemover->remove($class, $members, $editor);
        $this->overrideAttributeStripper->strip($class, $members, $methods, $editor);
        $editor->removeAttributes(
            $classNode->attrGroups,
            static fn(Attribute $attribute): bool => in_array(AttributeName::of($attribute), self::TRAIT_INCOMPATIBLE_ATTRIBUTES, true),
        );
    }

    /**
     * Turns the declaration header into `trait $traitName`
     *
     * Before the name, the class modifiers are removed (with the whitespace after them) and the `class`/`enum`
     * keyword becomes `trait`; the first T_STRING is the name. With $removeClauses, every token between the name
     * and the opening '{' (backed type, `extends`, `implements`) is blanked; whitespace is kept to preserve the
     * original brace placement.
     */
    private function rewriteHeader(ClassLike $classNode, TokenStreamEditor $editor, string $traitName, bool $removeClauses): void
    {
        [$position, $lastPosition] = TokenStreamEditor::declarationRange($classNode);

        $nameFound = false;
        for (; $position <= $lastPosition; ++$position) {
            $token = $editor->token($position);
            if ($token === null) {
                continue;
            }
            if (!$nameFound) {
                if (in_array($token->id, self::CLASS_MODIFIERS, true)) {
                    $editor->removeWithInlineWhitespace($position);
                } elseif ($token->id === T_CLASS || $token->id === T_ENUM) {
                    $editor->replace($position, 'trait');
                } elseif ($token->id === T_STRING) {
                    $editor->replace($position, $traitName);
                    $nameFound = true;
                    if (!$removeClauses) {
                        return;
                    }
                }
                continue;
            }
            if ($token->text === '{') {
                return;
            }
            if ($token->id !== T_WHITESPACE) {
                $editor->blank($position, $position);
            }
        }
    }

    /**
     * Removes the enum case declarations
     *
     * Only the case tokens are removed, the whitespace after each case is kept, so blank lines take the place of the
     * removed cases and the following methods stay on their original lines.
     */
    private function removeEnumCases(ClassLike $enumNode, TokenStreamEditor $editor): void
    {
        foreach ($enumNode->stmts as $statement) {
            if (!$statement instanceof EnumCase) {
                continue;
            }
            $caseRange = TokenStreamEditor::rangeOf($statement);
            if ($caseRange !== null) {
                $editor->remove(...$caseRange);
            }
        }
    }
}
