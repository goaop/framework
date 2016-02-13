<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Go\Aop\DynamicPointFilter;
use Go\Aop\Intercept\Interceptor;
use Go\Aop\Intercept\Invocation;
use Go\Aop\Intercept\Joinpoint;
use Serializable;

/**
 * Dynamic invocation matcher combines a pointcut and interceptor.
 *
 * For each invocation interceptor asks the pointcut if it matches the invocation.
 * Matcher will receive reflection point, object instance and invocation arguments to make a decision
 */
class DynamicInvocationMatcherInterceptor implements Interceptor, Serializable
{
    /**
     * Instance of pointcut to dynamically match joinpoints with args
     *
     * @var DynamicPointFilter
     */
    protected $dynamicPointFilter;

    /**
     * Overloaded property for storing instance of Interceptor
     *
     * @var Interceptor
     */
    protected $interceptor;

    /**
     * Dynamic matcher constructor
     *
     * @param DynamicPointFilter $pointFilter Instance of dynamic matcher
     * @param Interceptor $interceptor Instance of interceptor to invoke
     */
    public function __construct(DynamicPointFilter $pointFilter, Interceptor $interceptor)
    {
        $this->dynamicPointFilter = $pointFilter;
        $this->interceptor        = $interceptor;
    }

    /**
     * Serializes an interceptor into string representation
     *
     * @return string the string representation of the object or null
     */
    public function serialize()
    {
        return serialize([$this->dynamicPointFilter, $this->interceptor]);
    }

    /**
     * Unserialize an interceptor from the string
     *
     * @param string $serialized The string representation of the object.
     * @return void
     */
    public function unserialize($serialized)
    {
        list($this->dynamicPointFilter, $this->interceptor) = unserialize($serialized);
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
            if ($this->dynamicPointFilter->matches($joinpoint->getStaticPart(), $joinpoint->getThis(), $joinpoint->getArguments())) {
                return $this->interceptor->invoke($joinpoint);
            }
        }

        return $joinpoint->proceed();
    }
}
