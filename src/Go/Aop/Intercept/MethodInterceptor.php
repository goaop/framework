<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Intercept;

/**
 * Intercepts calls on an interface on its way to the target. These
 * are nested "on top" of the target.
 *
 * <p>The user should implement the {@link invoke(MethodInvocation)}
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
     * after the invocation. Polite implementations would certainly
     * like to invoke {@link Joinpoint::proceed()}.
     *
     * @param MethodInvocation $invocation the method invocation joinpoint
     * @return mixed the result of the call to {@link Joinpoint::proceed()},
     * might be intercepted by the interceptor.
     */
    public function invoke(MethodInvocation $invocation);
}