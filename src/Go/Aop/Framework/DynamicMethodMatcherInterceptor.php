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
class DynamicMethodMatcherInterceptor extends BaseInterceptor implements MethodInterceptor
{

    /**
     * Dynamic matcher constructor
     *
     * @param DynamicMethodMatcher $matcher Instance of dynamic matcher
     * @param MethodInterceptor $interceptor Instance of method interceptor to invoke
     */
    public function __construct(DynamicMethodMatcher $matcher, MethodInterceptor $interceptor)
    {
        $this->pointcut     = $matcher;
        $this->adviceMethod = $interceptor;
    }

    /**
     * Serializes an interceptor into string representation
     *
     * @return string the string representation of the object or null
     */
    public function serialize()
    {
        return serialize(array($this->pointcut, $this->adviceMethod));
    }

    /**
     * Unserialize an interceptor from the string
     *
     * @param string $serialized The string representation of the object.
     * @return void
     */
    public function unserialize($serialized)
    {
        list($this->pointcut, $this->adviceMethod) = unserialize(($serialized));
    }

    /**
     * Method invoker
     *
     * @param $invocation MethodInvocation the method invocation joinpoint
     * @return mixed the result of the call to {@see Joinpoint->proceed()}
     */
    final public function invoke(MethodInvocation $invocation)
    {
        if ($this->pointcut->matches($invocation->getMethod(), $invocation->getThis(), $invocation->getArguments())) {
            return $this->adviceMethod->invoke($invocation);
        } else {
            return $invocation->proceed();
        }
    }
}
