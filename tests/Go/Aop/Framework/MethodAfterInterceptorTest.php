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

class MethodAfterInterceptorTest extends AbstractMethodInterceptorTest
{

    public function testAdviceIsCalledAfterInvocation()
    {
        $sequence   = array();
        $advice     = $this->getAdvice($sequence);
        $invocation = $this->getInvocation($sequence);

        $interceptor = new MethodAfterInterceptor($advice);
        $result = $interceptor->invoke($invocation);

        $this->assertEquals('invocation', $result, "Advice should not affect the return value of invocation");
        $this->assertEquals(array('invocation', 'advice'), $sequence, "After advice should be invoked after invocation");
    }

    public function testAdviceIsCalledAfterExceptionInInvocation()
    {
        $sequence   = array();
        $advice     = $this->getAdvice($sequence);
        $invocation = $this->getInvocation($sequence, true);

        $interceptor = new MethodAfterInterceptor($advice);
        $this->setExpectedException('RuntimeException');
        try {
            $interceptor->invoke($invocation);
        } catch (\Exception $e) {
            $this->assertEquals(array('invocation', 'advice'), $sequence, "After advice should be invoked after invocation");
            throw $e;
        }
    }
}
 