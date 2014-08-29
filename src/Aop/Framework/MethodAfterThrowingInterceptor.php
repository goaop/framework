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

use Exception;
use Go\Aop\AdviceAfter;
use Go\Aop\Intercept\MethodInvocation;
use Go\Aop\Intercept\MethodInterceptor;

/**
 * "After Throwing" interceptor of method invocation
 */
class MethodAfterThrowingInterceptor extends BaseInterceptor implements MethodInterceptor, AdviceAfter
{
    /**
     * After throwing exception invoker
     *
     * @param $invocation MethodInvocation the method invocation joinpoint
     * @return mixed the result of the call to {@link Joinpoint::proceed()}
     * @throws Exception
     */
    final public function invoke(MethodInvocation $invocation)
    {
        $result = null;
        try {
            $result = $invocation->proceed();
        } catch (Exception $invocationException) {
            $adviceMethod = $this->adviceMethod;
            $adviceMethod($invocation);

            throw $invocationException;
        }

        return $result;
    }
}
