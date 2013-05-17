<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2013, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Framework;

use Exception;

use Go\Aop\AdviceAround;
use Go\Aop\Intercept\FunctionInvocation;
use Go\Aop\Intercept\FunctionInterceptor;


/**
 * @package go
 */
class FunctionAroundInterceptor extends BaseInterceptor implements FunctionInterceptor, AdviceAround
{
    /**
     * Around invoker
     *
     * @param $invocation FunctionInvocation the function invocation joinpoint
     * @return mixed the result of the call to {@link Joinpoint::proceed()}
     * @throws Exception
     */
    final public function invoke(FunctionInvocation $invocation)
    {
        $adviceMethod = $this->adviceMethod;
        return $adviceMethod($invocation);
    }
}
