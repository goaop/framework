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
use Go\Aop\Intercept\ConstructorInvocation;
use Go\Aop\Intercept\ConstructorInterceptor;

/**
 * "Before" interceptor of constructor
 */
class ConstructorBeforeInterceptor extends BaseInterceptor implements ConstructorInterceptor, AdviceBefore
{

    /**
     * Implement this method to perform extra treatments before and
     * after the construction of a new object. Polite implementations
     * would certainly like to invoke {@link Joinpoint::proceed()}.
     *
     * @param ConstructorInvocation $invocation the construction joinpoint
     * @return mixed the newly created object, which is also the result of
     * the call to {@link Joinpoint::proceed()}, might be replaced by
     * the interceptor.
     */
    final public function construct(ConstructorInvocation $invocation)
    {
        $adviceMethod = $this->adviceMethod;
        $adviceMethod($invocation);

        return $invocation->proceed();
    }
}
