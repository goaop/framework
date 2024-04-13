<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2016, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Go\Aop\Pointcut;
use Go\ParserReflection\ReflectionFileNamespace;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Pointcut that matches only inherited items, this is useful to filter inherited members via !matchInherited()
 *
 * As it is used only inside class context for methods and properties, it rejects all other type of points
 */
final class MatchInheritedPointcut implements Pointcut
{
    public function matches(
        ReflectionClass|ReflectionFileNamespace                $context,
        ReflectionMethod|ReflectionProperty|ReflectionFunction $reflector = null,
        object|string                                          $instanceOrScope = null,
        array                                                  $arguments = null
    ): bool {
        // Inherited items can be only inside class context
        if (!$context instanceof ReflectionClass) {
            return false;
        }

        // With only one context given, we should always match, as we need more info about nested items
        if (!isset($reflector)) {
            return true;
        }

        // Inherited items can be only methods and properties and not ReflectionFunction for example
        if (!$reflector instanceof ReflectionMethod && !$reflector instanceof ReflectionProperty) {
            return false;
        }

        $declaringClassName = $reflector->getDeclaringClass()->name;

        return $context->name !== $declaringClassName && $context->isSubclassOf($declaringClassName);
    }

    public function getKind(): int
    {
        return Pointcut::KIND_METHOD | Pointcut::KIND_PROPERTY;
    }
}
