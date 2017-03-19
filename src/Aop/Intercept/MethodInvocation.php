<?php
declare(strict_types = 1);
/*
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
 */
interface MethodInvocation extends Invocation
{

    /**
     * Gets the method being called.
     *
     * @return ReflectionMethod|AnnotatedReflectionMethod the method being called.
     */
    public function getMethod() : ReflectionMethod;

    /**
     * Invokes current method invocation with all interceptors
     *
     * @param null|object|string $instance Invocation instance (class name for static methods)
     * @param array $arguments List of arguments for method invocation
     * @param array $variadicArguments Additional list of variadic arguments
     *
     * @return mixed Result of invocation
     */
    public function __invoke($instance = null, array $arguments = [], array $variadicArguments = []);
}
