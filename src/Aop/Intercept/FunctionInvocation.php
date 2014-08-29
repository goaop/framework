<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Intercept;

use ReflectionFunction;

/**
 * Description of an invocation to a function, given to an interceptor
 * upon function-call.
 *
 * A function invocation is a joinpoint and can be intercepted by a function
 * interceptor.
 *
 * @see FunctionInterceptor
 */
interface FunctionInvocation extends Invocation
{

    /**
     * Gets the function being called.
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
