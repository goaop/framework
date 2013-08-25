<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2013, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Framework;

class FieldBeforeInterceptorTest extends AbstractFieldInterceptorTest
{
    public function testAdviceIsCalledBeforeGet()
    {
        $sequence   = array();
        $advice     = $this->getAdvice($sequence);
        $invocation = $this->getInvocation($sequence);

        $interceptor = new FieldBeforeInterceptor($advice);
        $result = $interceptor->get($invocation);

        $this->assertEquals('invocation', $result, "Advice should not affect the return value of invocation");
        $this->assertEquals(array('advice', 'invocation'), $sequence, "Before advice should be invoked before invocation");
    }

    public function testAdviceIsCalledBeforeSet()
    {
        $sequence   = array();
        $advice     = $this->getAdvice($sequence);
        $invocation = $this->getInvocation($sequence);

        $interceptor = new FieldBeforeInterceptor($advice);
        $result = $interceptor->set($invocation);

        $this->assertEquals('invocation', $result, "Advice should not affect the return value of invocation");
        $this->assertEquals(array('advice', 'invocation'), $sequence, "Before advice should be invoked before invocation");
    }
}
 