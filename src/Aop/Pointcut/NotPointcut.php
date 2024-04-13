<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
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
 * Logical "not" pointcut filter.
 */
final readonly class NotPointcut implements Pointcut
{
    /**
     * Not constructor
     */
    public function __construct(private Pointcut $pointcut) {}

    /**
     * @return ($reflector is null ? true : bool)
     */
    public function matches(
        ReflectionClass|ReflectionFileNamespace                $context,
        ReflectionMethod|ReflectionProperty|ReflectionFunction $reflector = null,
        object|string                                          $instanceOrScope = null,
        array                                                  $arguments = null
    ): bool {
        // For Logical "not" expression without reflector, we should match statically for any context
        if (!isset($reflector)) {
            return true;
        }

        // Otherwise we return inverted result from static/dynamic matching
        return !$this->pointcut->matches($context, $reflector, $instanceOrScope, $arguments);
    }

    public function getKind(): int
    {
        return $this->pointcut->getKind();
    }
}
