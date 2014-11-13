<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Go\Aop\MethodMatcher;
use ReflectionMethod;
use TokenReflection\ReflectionMethod as ParsedReflectionMethod;

/**
 * True method pointcut matches all methods in specific class
 */
class TrueMethodPointcut extends StaticMethodMatcherPointcut implements MethodMatcher
{
    /**
     * Performs matching of point of code
     *
     * @param mixed $method Specific part of code, can be any Reflection class
     * @param null|string|object $instance Invocation instance or string for static calls
     * @param null|array $arguments Dynamic arguments for method
     *
     * @return bool
     */
    public function matches($method, $instance = null, array $arguments = null)
    {
        /** @var $method ReflectionMethod|ParsedReflectionMethod */
        if (!$method instanceof ReflectionMethod && !$method instanceof ParsedReflectionMethod) {
            return false;
        }

        return true;
    }
}
