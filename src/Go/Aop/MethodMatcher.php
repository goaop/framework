<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop;

/**
 * Part of a Pointcut: Checks whether the target method is eligible for advice.
 *
 * A MethodMatcher may be evaluated statically or at runtime (dynamically).
 * Static matching involves method and (possibly) method attributes. Dynamic matching also makes arguments for
 * a particular call available, and any effects of running previous advice applying to the joinpoint.
 *
 * If an implementation returns false from its isRuntime() method, evaluation can be performed statically,
 * and the result will be the same for all invocations of this method, whatever their arguments.
 * This means that if the isRuntime() method returns false, the 3-arg matches() method will never be invoked.
 *
 * If an implementation returns true from its 2-arg matches() method and its isRuntime() method returns true,
 * the 3-arg matches() method will be invoked immediately before each potential execution of the related advice,
 * to decide whether the advice should run. All previous advice, such as earlier interceptors in an interceptor chain,
 * will have run, so any state changes they have produced in parameters will be available at the time of evaluation.
 */
interface MethodMatcher extends PointFilter
{

    /**
     * Is this MethodMatcher dynamic or static
     *
     * Can be invoked when an AOP proxy is created, and need not be invoked again before each method invocation
     *
     * @return bool whether or not a runtime match via the 3-arg matches() method is required if static matching passed
     */
    public function isRuntime();
}
