<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop;

/**
 * Part of a Pointcut: Checks whether the target method is eligible for advice.
 *
 * A MethodMatcher may be evaluated statically or at runtime (dynamically).
 * Static matching involves method and (possibly) method attributes. Dynamic matching also makes arguments for
 * a particular call available, and any effects of running previous advice applying to the joinpoint.
 *
 * If point filter is not dynamic (self::KIND_DYNAMIC), evaluation can be performed statically,
 * and the result will be the same for all invocations of this method, whatever their arguments.
 *
 * If an implementation returns true from its 2-arg matches() method and filter is self::KIND_DYNAMIC,
 * the 3-arg matches() method will be invoked immediately before each potential execution of the related advice,
 * to decide whether the advice should run. All previous advice, such as earlier interceptors in an interceptor chain,
 * will have run, so any state changes they have produced in parameters will be available at the time of evaluation.
 */
interface MethodMatcher extends PointFilter
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
    public function matches($method, $instance = null, array $arguments = null);
}
