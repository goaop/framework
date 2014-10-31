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
use Go\Aop\Intercept\Joinpoint;
use Go\Aop\Intercept\MethodInvocation;

/**
 * "Before" interceptor of method invocation
 */
class MethodBeforeInterceptor extends BaseInterceptor implements AdviceBefore
{
    /**
     * Before invoker
     *
     * @param MethodInvocation $joinpoint the method invocation joinpoint
     *
     * @return mixed the result of the call to {@link Joinpoint::proceed()},
     */
    final public function invoke(Joinpoint $joinpoint)
    {
        $adviceMethod = $this->adviceMethod;
        $adviceMethod($joinpoint);

        return $joinpoint->proceed();
    }
}
