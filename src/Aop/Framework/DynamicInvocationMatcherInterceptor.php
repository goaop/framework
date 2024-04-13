<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Go\Aop\Intercept\Interceptor;
use Go\Aop\Intercept\Joinpoint;
use Go\Aop\Intercept\MethodInvocation;
use Go\Aop\Pointcut;
use ReflectionClass;

/**
 * Dynamic invocation matcher combines a pointcut and interceptor.
 *
 * For each invocation interceptor asks the pointcut if it matches the invocation.
 * Matcher will receive reflection point, object instance and invocation arguments to make a decision
 */
readonly class DynamicInvocationMatcherInterceptor implements Interceptor
{
    /**
     * Dynamic invocation matcher constructor
     */
    public function __construct(
        private Pointcut    $pointcut,
        private Interceptor $interceptor
    ){}

    final public function invoke(Joinpoint $joinpoint): mixed
    {
        if ($joinpoint instanceof MethodInvocation) {
            $method       = $joinpoint->getMethod();
            $context      = $joinpoint->getThis() ?? $joinpoint->getScope();
            $contextClass = new ReflectionClass($context);
            if ($this->pointcut->matches($contextClass, $method, $context, $joinpoint->getArguments())) {
                return $this->interceptor->invoke($joinpoint);
            }
        }

        return $joinpoint->proceed();
    }
}
