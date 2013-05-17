<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2013, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Intercept;

/**
 * Intercepts calls on an interface on its way to the target. These
 * are nested "on top" of the target.
 *
 * <p>The user should implement the {@link invoke(FunctionInvocation)}
 * method to modify the original behavior. E.g. the following class
 * implements a tracing interceptor (traces all the calls on the
 * intercepted method(s)):
 *
 * <pre class=code>
 * class TracingInterceptor implements FunctionInterceptor {
 *   public function invoke(FunctionInvocation $i) {
 *     print("method ".$i->getFunction()." is called".
 *                        " with args ".$i->getArguments());
 *     $ret=$i->proceed();
 *     print("function ".$i->getFunction()." returns ".$ret);
 *     return $ret;
 *   }
 * }
 * </pre>
 */
interface FunctionInterceptor extends Interceptor
{

    /**
     * Implement this method to perform extra treatments before and
     * after the invocation. Polite implementations would certainly
     * like to invoke {@link Joinpoint::proceed()}.
     *
     * @param FunctionInvocation $invocation the function invocation joinpoint
     * @return mixed the result of the call to {@link Joinpoint::proceed()},
     * might be intercepted by the interceptor.
     */
    public function invoke(FunctionInvocation $invocation);
}