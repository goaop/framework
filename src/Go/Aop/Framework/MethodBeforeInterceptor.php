<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Framework;

use Go\Aop\AdviceBefore;
use Go\Aop\Intercept\MethodInvocation;
use Go\Aop\Intercept\MethodInterceptor;

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
        $adviceMethod = $this->adviceMethod;
        $adviceMethod($invocation);

        return $invocation->proceed();
    }
}
