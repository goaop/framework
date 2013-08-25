<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2013, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Framework;

use Go\Aop\Intercept\FunctionInvocation;

/**
 * Class FunctionAroundInterceptorTest
 *
 * @method \Go\Aop\Intercept\FunctionInvocation getInvocation(&$sequenceRecorder, $throwException = false)
 */
class FunctionAroundInterceptorTest extends AbstractInterceptorTest
{
    /**
     * Concrete class name for mock, should be redefined with LSB
     */
    const INVOCATION_CLASS = 'Go\Aop\Intercept\FunctionInvocation';

    public function testInvocationIsNotCalledWithoutProceed()
    {
        $sequence   = array();
        $advice     = $this->getAdvice($sequence); // advice will not call Invocation->proceed()
        $invocation = $this->getInvocation($sequence);

        $interceptor = new FunctionAroundInterceptor($advice);
        $result = $interceptor->invoke($invocation);

        $this->assertEquals('advice', $result, "Advice should change the return value of invocation");
        $this->assertEquals(array('advice'), $sequence, "Only advice should be invoked");
    }

    public function testInvocationIsCalledWithinAdvice()
    {
        $sequence   = array();
        $advice     = function (FunctionInvocation $invocation) use (&$sequence) {
            $sequence[] = 'advice';
            $result = $invocation->proceed();
            $sequence[] = 'advice';
            return $result;
        };
        $invocation = $this->getInvocation($sequence);

        $interceptor = new FunctionAroundInterceptor($advice);
        $result = $interceptor->invoke($invocation);
        $this->assertEquals('invocation', $result, "Advice should return an original return value");
        $this->assertEquals(array('advice', 'invocation', 'advice'), $sequence, "Around logic should work");
    }
}
 