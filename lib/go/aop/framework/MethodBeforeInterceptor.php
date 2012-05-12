<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace go\aop\framework;

use org\aopalliance\intercept\MethodInvocation;
use org\aopalliance\intercept\MethodInterceptor;
use go\aop\AdviceBefore;

/**
 * @package go
 */
class MethodBeforeInterceptor extends BaseInterceptor implements MethodInterceptor, AdviceBefore
{
    /**
     * Before invoker
     *
     * @param MethodInvocation $invocation the method invocation joinpoint
     * @return mixed the result of the call to {@link Joinpoint::proceed()},
     */
    final public function invoke(MethodInvocation $invocation)
    {
        $this->invokeAdviceForJoinpoint($invocation);
        return $invocation->proceed();
    }
}
