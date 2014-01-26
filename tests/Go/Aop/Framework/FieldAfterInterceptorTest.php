<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

class FieldAfterInterceptorTest extends AbstractFieldInterceptorTest
{
    public function testAdviceIsCalledAfterGet()
    {
        $sequence   = array();
        $advice     = $this->getAdvice($sequence);
        $invocation = $this->getInvocation($sequence);

        $interceptor = new FieldAfterInterceptor($advice);
        $result = $interceptor->get($invocation);

        $this->assertEquals('invocation', $result, "Advice should not affect the return value of invocation");
        $this->assertEquals(array('invocation', 'advice'), $sequence, "After advice should be invoked after invocation");
    }

    public function testAdviceIsCalledAfterSet()
    {
        $sequence   = array();
        $advice     = $this->getAdvice($sequence);
        $invocation = $this->getInvocation($sequence);

        $interceptor = new FieldAfterInterceptor($advice);
        $result = $interceptor->set($invocation);

        $this->assertEquals('invocation', $result, "Advice should not affect the return value of invocation");
        $this->assertEquals(array('invocation', 'advice'), $sequence, "After advice should be invoked after invocation");
    }
}
 