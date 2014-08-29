<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Intercept;

use Go\Aop\Support\AnnotatedReflectionMethod;
use ReflectionMethod;

/**
 * Description of an invocation to a method, given to an interceptor
 * upon method-call.
 *
 * A method invocation is a joinpoint and can be intercepted by a method
 * interceptor.
 *
 * @see MethodInterceptor
 */
interface MethodInvocation extends Invocation
{

    /**
     * Gets the method being called.
     *
     * @return ReflectionMethod|AnnotatedReflectionMethod the method being called.
     */
    public function getMethod();

    /**
     * Invokes current method invocation with all interceptors
     *
     * @return mixed
     */
    public function __invoke();
}
