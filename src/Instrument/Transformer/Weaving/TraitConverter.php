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

use Closure;
use Go\Aop\Exception\WeavingException;
use Go\Aop\Framework\GeneratedInterceptor;
use Go\Core\AspectContainer;
use Go\ParserReflection\ReflectionClass;
use Go\ParserReflection\ReflectionMethod;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Property;
use ReflectionMethod as NativeReflectionMethod;

/**
 * Converts the source of an advised class, enum or trait into the trait its proxy uses
 *
 * The woven trait keeps the original method bodies on their original lines (XDebug breakpoint mapping), the proxy
 * generated next to it re-declares what the trait can not hold:
 * - Class: `class` becomes `trait`, the modifiers and the `extends`/`implements` clauses are removed, so are the
 *   class attributes that are invalid on traits (issue #615), intercepted properties and promoted property modifiers.
 * - Enum: `enum` becomes `trait`, the backed type, the `implements` clause and every case are removed.
 * - Trait: renamed and stripped of intercepted properties, TraitProxyGenerator generates a child trait using it.
 *
 * #[\Override] is stripped from every intercepted own method: PHP copies the attributes of a method to its trait
 * alias in the proxy (`m as private mOriginalAlias`), which overrides nothing. The proxy method keeps the attribute.
 *
 * Every edit is addressed by the token positions of the AST nodes involved, token loops stay within those ranges.
 *
 * @internal
 */
final class TraitConverter
{
    /**
     * Class modifiers a trait declaration can not have
     */
    private const array CLASS_MODIFIERS = [T_FINAL, T_ABSTRACT, T_READONLY];

    /**
     * Modifiers that make a constructor parameter a promoted property
     */
    private const array PROMOTION_MODIFIERS = [
        T_PUBLIC, T_PROTECTED, T_PRIVATE,
        T_PUBLIC_SET, T_PROTECTED_SET, T_PRIVATE_SET,
        T_READONLY, T_FINAL,
    ];

    /**
     * Class-level attributes that are compile-time invalid on traits ("Cannot apply #[\Attribute] to trait")
     *
     * The proxy class re-declares them from the AST via AttributeGroupsGenerator, so attribute classes keep working.
     */
    private const array TRAIT_INCOMPATIBLE_ATTRIBUTES = ['Attribute', 'AllowDynamicProperties'];

    /**
     * Converts the declaration of the class-like into the trait $traitName, in the token stream of its file
     *
     * @param array<string, array<string, list<string|GeneratedInterceptor>>> $advices Sorted advices of the class
     * @param array<string, NativeReflectionMethod>                          $methods Methods of the class by name
     *
     * @throws WeavingException When an intercepted method can not be aliased, or the declaration has no tokens
     */
    public function convert(
        ReflectionClass $class,
        array $advices,
        array $methods,
        TokenStreamEditor $editor,
        string $traitName,
    ): void {
        $interceptedMethods = [
            ...array_keys($advices[AspectContainer::METHOD_PREFIX] ?? []),
            ...array_keys($advices[AspectContainer::STATIC_METHOD_PREFIX] ?? []),
        ];
        $interceptedProperties = array_flip(array_keys($advices[AspectContainer::PROPERTY_PREFIX] ?? []));
        $this->assertNoTraitImportedOverride($class, $interceptedMethods, $methods);

        $classNode = $class->getNode();
        if ($class->isTrait()) {
            $this->removeInterceptedProperties($class, $interceptedProperties, $editor);
            $this->rewriteHeader($classNode, $editor, $traitName);
            $this->stripOverrideAttributes($class, $interceptedMethods, $methods, $editor);

            return;
        }
        if ($class->isEnum()) {
            $this->rewriteHeader($classNode, $editor, $traitName);
            // Cases can not exist in traits, EnumProxyGenerator re-declares them in the proxy enum. The whitespace
            // after each case is kept: blank lines take the place of the cases, the methods keep their lines
            foreach ($classNode->stmts as $statement) {
                if ($statement instanceof EnumCase) {
                    $editor->remove(...TokenStreamEditor::range($statement));
                }
            }
            $this->stripOverrideAttributes($class, $interceptedMethods, $methods, $editor);

            return;
        }
        $this->rewriteHeader($classNode, $editor, $traitName);
        $this->removeInterceptedProperties($class, $interceptedProperties, $editor);
        $this->stripOverrideAttributes($class, $interceptedMethods, $methods, $editor);
        $this->removeAttributes(
            $classNode->attrGroups,
            static fn(Attribute $attribute): bool => in_array(self::attributeName($attribute), self::TRAIT_INCOMPATIBLE_ATTRIBUTES, true),
            $editor,
        );
    }

    /**
     * Turns the declaration header into `trait $traitName`
     *
     * Between the attribute groups and the name, the class modifiers are removed (with the whitespace after them)
     * and the `class`/`enum` keyword becomes `trait`. The `extends`/`implements` clauses and the backed type of an
     * enum are blanked, their whitespace is kept to preserve the original brace placement.
     */
    private function rewriteHeader(ClassLike $classNode, TokenStreamEditor $editor, string $traitName): void
    {
        // Anonymous classes are never woven
        if ($classNode->name === null) {
            return;
        }
        [$headerStart]  = TokenStreamEditor::rangeAfterAttributes($classNode);
        [$namePosition] = TokenStreamEditor::range($classNode->name);
        $editor->replace($namePosition, $traitName);

        for ($position = $headerStart; $position < $namePosition; ++$position) {
            $token = $editor->token($position);
            if ($token === null) {
                continue;
            }
            if (in_array($token->id, self::CLASS_MODIFIERS, true)) {
                $editor->removeWithInlineWhitespace($position);
            } elseif ($token->id === T_CLASS || $token->id === T_ENUM) {
                $editor->replace($position, 'trait');
            }
        }

        $clauses = match (true) {
            $classNode instanceof Class_ => array_filter([$classNode->extends, ...$classNode->implements]),
            $classNode instanceof Enum_  => array_filter([$classNode->scalarType, ...$classNode->implements]),
            default                      => [],
        };
        $lastClause = end($clauses);
        if ($lastClause !== false) {
            $editor->blank($namePosition + 1, TokenStreamEditor::range($lastClause)[1], keepWhitespace: true);
        }
    }

    /**
     * Rejects the interception of a method imported from a trait that carries #[\Override] (issue #761)
     *
     * The attribute of a trait method lives in the trait file, which is shared by every class using it and can not be
     * stripped per class. Native reflection hides the case: it reports the using class as the declaring class of a
     * trait method. Trait methods arriving through a parent class keep the parent in their `class` property and are
     * called through `parent::m(...)` without an alias, so they pass.
     *
     * @param list<string>                          $interceptedMethods
     * @param array<string, NativeReflectionMethod> $methods
     */
    private function assertNoTraitImportedOverride(ReflectionClass $class, array $interceptedMethods, array $methods): void
    {
        foreach ($interceptedMethods as $methodName) {
            $method = $methods[$methodName] ?? null;
            // Methods inherited from a parent class (including the ones it imports from traits) are not aliased
            if (!$method instanceof ReflectionMethod || $method->class !== $class->name) {
                continue;
            }
            $declaringClass = $method->getDeclaringClass();
            if ($declaringClass->name === $class->name || !$declaringClass->isTrait()) {
                continue;
            }
            foreach ($method->getNode()->attrGroups as $attrGroup) {
                foreach ($attrGroup->attrs as $attribute) {
                    if (self::isOverride($attribute)) {
                        throw WeavingException::traitImportedOverrideNotInterceptable(
                            $class->name,
                            $methodName,
                            $declaringClass->name,
                        );
                    }
                }
            }
        }
    }

    /**
     * Removes #[\Override] from every intercepted method declared in the class body
     *
     * @param list<string>                          $interceptedMethods
     * @param array<string, NativeReflectionMethod> $methods
     */
    private function stripOverrideAttributes(
        ReflectionClass $class,
        array $interceptedMethods,
        array $methods,
        TokenStreamEditor $editor,
    ): void {
        foreach ($interceptedMethods as $methodName) {
            // The declaring class, not the `class` property: a method imported from a trait reports the using
            // class there, while its attributes live in the trait source (rejected by assertNoTraitImportedOverride)
            $method = $methods[$methodName] ?? null;
            if ($method instanceof ReflectionMethod && $method->getDeclaringClass()->name === $class->name) {
                $this->removeAttributes($method->getNode()->attrGroups, self::isOverride(...), $editor);
            }
        }
    }

    /**
     * Removes the attributes selected by $shouldRemove from the attribute groups
     *
     * A group whose attributes are all removed is blanked entirely. Otherwise every selected entry is blanked with
     * one comma: the one after it (before the next entry or the closing bracket), else the one before it.
     *
     * @param AttributeGroup[]         $attrGroups
     * @param Closure(Attribute): bool $shouldRemove
     */
    private function removeAttributes(array $attrGroups, Closure $shouldRemove, TokenStreamEditor $editor): void
    {
        foreach ($attrGroups as $attrGroup) {
            $attributes         = array_values($attrGroup->attrs);
            $attributesToRemove = array_filter($attributes, $shouldRemove);
            if ($attributesToRemove === []) {
                continue;
            }
            [$groupStart, $groupEnd] = TokenStreamEditor::range($attrGroup);
            if (count($attributesToRemove) === count($attributes)) {
                $editor->blank($groupStart, $groupEnd);
                continue;
            }
            foreach ($attributesToRemove as $index => $attribute) {
                [$start, $end] = TokenStreamEditor::range($attribute);
                $editor->blank($start, $end);

                $nextStart     = isset($attributes[$index + 1]) ? TokenStreamEditor::range($attributes[$index + 1])[0] : $groupEnd;
                $trailingComma = $editor->find(',', $end + 1, $nextStart - 1);
                if ($trailingComma !== null) {
                    $editor->removeWithInlineWhitespace($trailingComma);
                    continue;
                }
                $previousEnd  = isset($attributes[$index - 1]) ? TokenStreamEditor::range($attributes[$index - 1])[1] : $groupStart;
                $leadingComma = $editor->find(',', $previousEnd + 1, $start - 1);
                if ($leadingComma !== null) {
                    $editor->remove($leadingComma, $leadingComma);
                }
            }
        }
    }

    /**
     * Removes the declarations of intercepted properties, the proxy re-declares them with native PHP 8.4 hooks
     *
     * Plain declarations are removed per item ({@see removePropertyItems()}). A promoted constructor property can not
     * be removed, it doubles as the constructor parameter (issue #599): its promotion modifiers are removed and the
     * constructor body starts with an assignment to the property, which goes through the set hook of the proxy.
     *
     * @param array<string, int> $interceptedProperties Intercepted property names as keys
     */
    private function removeInterceptedProperties(
        ReflectionClass $class,
        array $interceptedProperties,
        TokenStreamEditor $editor,
    ): void {
        if ($interceptedProperties === []) {
            return;
        }
        $classNode = $class->getNode();
        foreach ($classNode->stmts as $statement) {
            if ($statement instanceof Property) {
                $this->removePropertyItems($class->name, $statement, $interceptedProperties, $editor);
            }
        }
        $constructor = $classNode->getMethod('__construct');
        if ($class->isTrait() || $constructor === null) {
            return;
        }

        $assignments  = [];
        $signatureEnd = TokenStreamEditor::range($constructor)[0];
        foreach ($constructor->params as $parameter) {
            $signatureEnd = TokenStreamEditor::range($parameter)[1];
            $propertyName = $parameter->var instanceof Variable ? $parameter->var->name : null;
            if ($parameter->isPromoted() && is_string($propertyName) && isset($interceptedProperties[$propertyName])) {
                $this->removePromotionModifiers($parameter, $editor);
                $assignments[] = sprintf('$this->%1$s = $%1$s;', $propertyName);
            }
        }
        if ($assignments !== []) {
            $this->prependToConstructorBody($constructor, $signatureEnd, $assignments, $editor);
        }
    }

    /**
     * Removes the intercepted items of one property declaration statement
     *
     * When every item is intercepted, the whole statement (attributes, modifiers, type, items and the semicolon) is
     * blanked. Otherwise only the intercepted items are blanked together with the commas that separated them, so
     * `public int $a = 1, $b;` with `$a` intercepted keeps `public int $b;`. A newline-free marker comment takes the
     * place of every removed item; never a `//` prefix, it would leave multi-line declarations live (issue #669).
     *
     * @param array<string, int> $interceptedProperties Intercepted property names as keys
     */
    private function removePropertyItems(
        string $className,
        Property $statement,
        array $interceptedProperties,
        TokenStreamEditor $editor,
    ): void {
        $items     = [];
        $isRemoved = [];
        $markers   = [];
        foreach (array_values($statement->props) as $index => $item) {
            $items[$index]     = TokenStreamEditor::range($item);
            $propertyName      = $item->name->name;
            $isRemoved[$index] = isset($interceptedProperties[$propertyName]);
            if ($isRemoved[$index]) {
                $markers[$index] = sprintf('/* Moved by weaving interceptor to the {@see %s->%s} */', $className, $propertyName);
            }
        }
        if ($markers === []) {
            return;
        }
        if (count($markers) === count($items)) {
            [$start, $end] = TokenStreamEditor::range($statement);
            $editor->blank($start, $end, implode(' ', $markers));

            return;
        }

        $lastIndex = array_key_last($items);
        foreach ($items as $index => [$start, $end]) {
            if ($isRemoved[$index]) {
                $editor->blank($start, $end, $markers[$index]);
            }
            if ($index === $lastIndex) {
                break;
            }
            // Exactly one comma separates two items; it survives only between two kept items:
            // it follows a kept item and another kept item comes later in the statement
            $keepsComma = !$isRemoved[$index] && in_array(false, array_slice($isRemoved, $index + 1), true);
            $comma      = $keepsComma ? null : $editor->find(',', $end + 1, $items[$index + 1][0] - 1);
            if ($comma !== null) {
                $editor->replace($comma, '');
            }
        }
    }

    /**
     * Removes the promotion modifiers (visibility, asymmetric visibility, readonly, final) of a constructor parameter
     *
     * The modifiers lie between the attribute groups and the type (or the variable); attributes, type, name and
     * default value are kept.
     */
    private function removePromotionModifiers(Param $parameter, TokenStreamEditor $editor): void
    {
        [$start]       = TokenStreamEditor::rangeAfterAttributes($parameter);
        [$typeOrName]  = TokenStreamEditor::range($parameter->type ?? $parameter->var);
        for ($position = $start; $position < $typeOrName; ++$position) {
            if (in_array($editor->token($position)?->id, self::PROMOTION_MODIFIERS, true)) {
                $editor->removeWithInlineWhitespace($position);
            }
        }
    }

    /**
     * Puts the assignments right after the '{' opening the constructor body, on the same line (line numbers)
     *
     * The body brace is the first '{' after the last parameter: hook bodies and closures in default values lie within
     * the parameter ranges. An abstract constructor has no body and gets nothing.
     *
     * @param non-empty-list<string> $assignments Assignment statements like '$this->name = $name;'
     */
    private function prependToConstructorBody(
        ClassMethod $constructor,
        int $signatureEnd,
        array $assignments,
        TokenStreamEditor $editor,
    ): void {
        $bodyStart = $editor->find('{', $signatureEnd + 1, TokenStreamEditor::range($constructor)[1]);
        if ($bodyStart !== null) {
            $editor->append($bodyStart, ' ' . implode(' ', $assignments));
        }
    }

    /**
     * Checks whether the attribute is #[\Override], aliases and qualified names included
     */
    private static function isOverride(Attribute $attribute): bool
    {
        return self::attributeName($attribute) === 'Override';
    }

    /**
     * Returns the fully qualified name of an attribute class, without a leading backslash
     *
     * parser-reflection runs the NameResolver without replacing nodes, so the resolved name is kept in the
     * `resolvedName` attribute of the name node; the name as written is the fallback.
     */
    private static function attributeName(Attribute $attribute): string
    {
        $resolvedName = $attribute->name->getAttribute('resolvedName');
        $name         = $resolvedName instanceof Name ? $resolvedName : $attribute->name;

        return ltrim($name->toString(), '\\');
    }
}
