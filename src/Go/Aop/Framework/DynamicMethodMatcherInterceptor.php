<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2013, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Framework;

use Go\Aop\Intercept\MethodInvocation;
use Go\Aop\Intercept\MethodInterceptor;
use Go\Aop\Support\DynamicMethodMatcher;


/**
 * Dynamic method matcher combines a pointcut and interceptor.
 *
 * For each invocation interceptor asks the pointcut if it matches the invocation.
 * Matcher will receive reflection point, object instance and invocation arguments to make a decision
 *
 * @package go
 */
class DynamicMethodMatcherInterceptor implements MethodInterceptor
{

    /**
     * Instance of dynamic matcher
     *
     * @var DynamicMethodMatcher
     */
    private $matcher;

    /**
     * Instance of method interceptor to invoke
     *
     * @var MethodInterceptor
     */
    private $interceptor;

    /**
     * Dynamic matcher constructor
     *
     * @param DynamicMethodMatcher $matcher Instance of dynamic matcher
     * @param MethodInterceptor $interceptor Instance of method interceptor to invoke
     */
    public function __construct(DynamicMethodMatcher $matcher, MethodInterceptor $interceptor)
    {
        $this->matcher     = $matcher;
        $this->interceptor = $interceptor;
    }

    /**
     * Method invoker
     *
     * @param $invocation MethodInvocation the method invocation joinpoint
     * @return mixed the result of the call to {@see Joinpoint->proceed()}
     */
    final public function invoke(MethodInvocation $invocation)
    {
        if ($this->matcher->matches($invocation->getMethod(), $invocation->getThis(), $invocation->getArguments())) {
            return $this->interceptor->invoke($invocation);
        } else {
            return $invocation->proceed();
        }
    }
}
