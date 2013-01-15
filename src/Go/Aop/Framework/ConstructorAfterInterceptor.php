<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Framework;

use Exception;

use Go\Aop\AdviceAfter;
use Go\Aop\Intercept\ConstructorInvocation;
use Go\Aop\Intercept\ConstructorInterceptor;


/**
 * @package go
 */
class ConstructorAfterInterceptor extends BaseInterceptor implements ConstructorInterceptor, AdviceAfter
{

    /**
     * Implement this method to perform extra treatments before and
     * after the construction of a new object. Polite implementations
     * would certainly like to invoke {@link Joinpoint::proceed()}.
     *
     * @param ConstructorInvocation $invocation the construction joinpoint
     * @throws Exception if exception was thrown in constructor
     *
     * @return mixed the newly created object, which is also the result of
     * the call to {@link Joinpoint::proceed()}, might be replaced by
     * the interceptor.
     */
    final public function construct(ConstructorInvocation $invocation)
    {
        $result = null;
        try {
            $result = $invocation->proceed();
        } catch (Exception $invocationException) {
            // this is need for finally emulation in PHP
        }

        $adviceMethod = $this->adviceMethod;
        $adviceMethod($invocation);

        if (isset($invocationException)) {
            throw $invocationException;
        }
        return $result;
    }
}
