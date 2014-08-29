<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Intercept;

/**
 * Interceptor of method execution
 *
 * The user should implement the invoke(MethodInvocation)
 * method to modify the original behavior. E.g. the following class
 * implements a tracing interceptor (traces all the calls on the
 * intercepted method(s)):
 *
 * <pre class=code>
 * class TracingInterceptor implements MethodInterceptor {
 *   public function invoke(MethodInvocation $i) {
 *     print("method ".$i->getMethod()." is called on ".
 *                        $i->getThis()." with args ".$i->getArguments());
 *     $ret=$i->proceed();
 *     print("method ".$i->getMethod()." returns ".$ret);
 *     return $ret;
 *   }
 * }
 * </pre>
 */
interface MethodInterceptor extends Interceptor
{

    /**
     * Implement this method to perform extra treatments before and
     * after the invocation.
     *
     * @param MethodInvocation $invocation the method invocation joinpoint
     * @return mixed the result of the call
     */
    public function invoke(MethodInvocation $invocation);
}
