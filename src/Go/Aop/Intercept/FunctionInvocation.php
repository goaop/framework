<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2013, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Intercept;

use ReflectionFunction;

/**
 * Description of an invocation to a function, given to an interceptor
 * upon function-call.
 *
 * <p>A function invocation is a joinpoint and can be intercepted by a function
 * interceptor.
 *
 * @see FunctionInterceptor
 */
interface FunctionInvocation extends Invocation
{

    /**
     * Gets the function being called.
     *
     * <p>This method is a friendly implementation of the
     * {@link Joinpoint::getStaticPart()} method (same result).
     *
     * @return ReflectionFunction the function being called.
     */
    public function getFunction();

    /**
     * Invokes current function invocation with all interceptors
     *
     * @return mixed
     */
    public function __invoke();
}
