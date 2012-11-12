<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Framework;

use Exception;

use Go\Aop\AdviceAround;
use Go\Aop\Intercept\MethodInvocation;
use Go\Aop\Intercept\MethodInterceptor;


/**
 * @package go
 */
class MethodAroundInterceptor extends BaseInterceptor implements MethodInterceptor, AdviceAround
{
    /**
     * Around invoker
     *
     * @param $invocation MethodInvocation the method invocation joinpoint
     * @return mixed the result of the call to {@link Joinpoint::proceed()}
     * @throws Exception
     */
    final public function invoke(MethodInvocation $invocation)
    {
        $adviceMethod = $this->adviceMethod;
        return $adviceMethod($invocation);
    }
}
