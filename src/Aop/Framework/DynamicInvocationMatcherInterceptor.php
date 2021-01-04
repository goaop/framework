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
use Go\Aop\PointFilter;
use ReflectionClass;

/**
 * Dynamic invocation matcher combines a pointcut and interceptor.
 *
 * For each invocation interceptor asks the pointcut if it matches the invocation.
 * Matcher will receive reflection point, object instance and invocation arguments to make a decision
 */
class DynamicInvocationMatcherInterceptor implements Interceptor
{
    /**
     * Instance of pointcut to dynamically match joinpoints with args
     */
    protected PointFilter $pointFilter;

    /**
     * Instance of interceptor to invoke
     */
    protected Interceptor $interceptor;

    /**
     * Dynamic matcher constructor
     */
    public function __construct(PointFilter $pointFilter, Interceptor $interceptor)
    {
        $this->pointFilter = $pointFilter;
        $this->interceptor = $interceptor;
    }

    /**
     * @inheritdoc
     */
    final public function invoke(Joinpoint $joinpoint)
    {
        if ($joinpoint instanceof MethodInvocation) {
            $method       = $joinpoint->getMethod();
            $context      = $joinpoint->getThis() ?? $joinpoint->getScope();
            $contextClass = new ReflectionClass($context);
            if ($this->pointFilter->matches($method, $contextClass, $context, $joinpoint->getArguments())) {
                return $this->interceptor->invoke($joinpoint);
            }
        }

        return $joinpoint->proceed();
    }
}
