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
use Go\Intercept\MethodInvocation;
use Go\Intercept\MethodInterceptor;


/**
 * @package go
 */
class MethodAfterInterceptor extends BaseInterceptor implements MethodInterceptor, AdviceAfter
{
    /**
     * After invoker
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
            // this is need for finally emulation in PHP
        }
        $this->invokeAdviceForJoinpoint($invocation);
        if (isset($invocationException)) {
            throw $invocationException;
        }
        return $result;
    }
}
