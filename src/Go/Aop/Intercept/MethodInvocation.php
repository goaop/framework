<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Intercept;

use ReflectionMethod;

/**
 * Description of an invocation to a method, given to an interceptor
 * upon method-call.
 *
 * <p>A method invocation is a joinpoint and can be intercepted by a method
 * interceptor.
 *
 * @see MethodInterceptor
 */
interface MethodInvocation extends Invocation
{

    /**
     * Gets the method being called.
     *
     * <p>This method is a friendly implementation of the
     * {@link Joinpoint::getStaticPart()} method (same result).
     *
     * @return ReflectionMethod the method being called.
     */
    public function getMethod();

    /**
     * Invokes current method invocation with all interceptors
     *
     * @return mixed
     */
    public function __invoke();
}
