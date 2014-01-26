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

use Go\Aop\Intercept\FieldAccess;

class FieldAroundInterceptorTest extends AbstractFieldInterceptorTest
{

    public function testGetIsNotCalledWithoutProceed()
    {
        $sequence   = array();
        $advice     = $this->getAdvice($sequence); // advice will not call Invocation->proceed()
        $invocation = $this->getInvocation($sequence);

        $interceptor = new FieldAroundInterceptor($advice);
        $result = $interceptor->get($invocation);

        $this->assertEquals('advice', $result, "Advice should change the return value of invocation");
        $this->assertEquals(array('advice'), $sequence, "Only advice should be invoked");
    }

    public function testSetIsNotCalledWithoutProceed()
    {
        $sequence   = array();
        $advice     = $this->getAdvice($sequence); // advice will not call Invocation->proceed()
        $invocation = $this->getInvocation($sequence);

        $interceptor = new FieldAroundInterceptor($advice);
        $result = $interceptor->set($invocation);

        $this->assertEquals('advice', $result, "Advice should change the return value of invocation");
        $this->assertEquals(array('advice'), $sequence, "Only advice should be invoked");
    }

    public function testGetIsCalledWithinAdvice()
    {
        $sequence   = array();
        $advice     = function (FieldAccess $invocation) use (&$sequence) {
            $sequence[] = 'advice';
            $result = $invocation->proceed();
            $sequence[] = 'advice';
            return $result;
        };
        $invocation = $this->getInvocation($sequence);

        $interceptor = new FieldAroundInterceptor($advice);
        $result = $interceptor->get($invocation);
        $this->assertEquals('invocation', $result, "Advice should return an original return value");
        $this->assertEquals(array('advice', 'invocation', 'advice'), $sequence, "Around logic should work");
    }

    public function testSetIsCalledWithinAdvice()
    {
        $sequence   = array();
        $advice     = function (FieldAccess $invocation) use (&$sequence) {
            $sequence[] = 'advice';
            $result = $invocation->proceed();
            $sequence[] = 'advice';
            return $result;
        };
        $invocation = $this->getInvocation($sequence);

        $interceptor = new FieldAroundInterceptor($advice);
        $result = $interceptor->set($invocation);
        $this->assertEquals('invocation', $result, "Advice should return an original return value");
        $this->assertEquals(array('advice', 'invocation', 'advice'), $sequence, "Around logic should work");
    }
}
 