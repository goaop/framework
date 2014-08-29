<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Go\Aop\AdviceBefore;
use Go\Aop\Intercept\MethodInvocation;
use Go\Aop\Intercept\MethodInterceptor;

/**
 * "Before" interceptor of method invocation
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
