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
use Go\Aop\Intercept\Invocation;
use Go\Aop\Intercept\Joinpoint;
use Go\Aop\PointFilter;

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
     *
     * @var PointFilter
     */
    protected $pointFilter;

    /**
     * Overloaded property for storing instance of Interceptor
     *
     * @var Interceptor
     */
    protected $interceptor;

    /**
     * Dynamic matcher constructor
     *
     * @param PointFilter $pointFilter Instance of dynamic matcher
     * @param Interceptor $interceptor Instance of interceptor to invoke
     */
    public function __construct(PointFilter $pointFilter, Interceptor $interceptor)
    {
        $this->pointFilter = $pointFilter;
        $this->interceptor = $interceptor;
    }

    /**
     * Method invoker
     *
     * @param Joinpoint $joinpoint the method invocation joinpoint
     *
     * @return mixed the result of the call to {@see Joinpoint->proceed()}
     */
    final public function invoke(Joinpoint $joinpoint)
    {
        if ($joinpoint instanceof Invocation) {
            $point    = $joinpoint->getStaticPart();
            $instance = $joinpoint->getThis();
            $context  = new \ReflectionClass($instance);
            if ($this->pointFilter->matches($point, $context, $instance, $joinpoint->getArguments())) {
                return $this->interceptor->invoke($joinpoint);
            }
        }

        return $joinpoint->proceed();
    }
}
