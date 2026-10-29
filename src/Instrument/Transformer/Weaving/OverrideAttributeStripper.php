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
use Go\ParserReflection\ReflectionMethod;
use ReflectionMethod as NativeReflectionMethod;

/**
 * Keeps #[\Override] away from the trait aliases of intercepted methods
 *
 * Every intercepted own method is aliased in the trait-use block of the proxy (`m as private mOriginalAlias`) and PHP
 * copies the attributes of the method to that alias, which overrides nothing: #[\Override] on it is a fatal error at
 * load time. The attribute is stripped from the woven source, the proxy method keeps it (MethodGenerator copies it
 * from the AST), so PHP still checks the override.
 *
 * @internal
 */
final class OverrideAttributeStripper
{
    /**
     * Rejects the interception of a method imported from a trait that carries #[\Override] (issue #761)
     *
     * The attribute of a trait method lives in the trait file, which is shared by every class using it and can not be
     * edited per class. The proxy generators can not detect the case: native reflection reports the using class as
     * the declaring class of a trait method.
     *
     * Covered: trait aliases (`use T { foo as bar; }`), static methods, enums (EnumProxyGenerator aliases every
     * intercepted method) and woven traits using another trait. Trait methods arriving through a parent class keep
     * the parent in their `class` property and are called through `parent::m(...)` without an alias, so they pass.
     * Methods of a trait used by an imported trait (nested traits) are not collected by parser-reflection 5.0.1, so
     * they can not get here.
     *
     * @param array<string, NativeReflectionMethod> $methods Methods of the class by name
     *
     * @throws WeavingException When such a method is intercepted
     */
    public function assertStrippable(ReflectionClass $class, InterceptedMembers $members, array $methods): void
    {
        foreach ($members->methodNames as $methodName) {
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
                    if (AttributeName::isOverride($attribute)) {
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
     * Applies to woven classes, enums and traits: the proxy trait of a woven trait aliases its intercepted methods
     * the same way.
     *
     * @param array<string, NativeReflectionMethod> $methods Methods of the class by name
     */
    public function strip(
        ReflectionClass $class,
        InterceptedMembers $members,
        array $methods,
        TokenStreamEditor $editor,
    ): void {
        foreach ($members->methodNames as $methodName) {
            // The declaring class, not the `class` property: a method imported from a trait reports the using
            // class there, while its attributes live in the trait source, not in this token stream. Interception
            // of such a method with #[\Override] is rejected earlier, by assertStrippable()
            $method = $methods[$methodName] ?? null;
            if (!$method instanceof ReflectionMethod || $method->getDeclaringClass()->name !== $class->name) {
                continue;
            }
            $editor->removeAttributes($method->getNode()->attrGroups, AttributeName::isOverride(...));
        }
    }
}
