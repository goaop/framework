<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Go\Aop\Intercept\Invocation;

class AroundInterceptorTest extends AbstractInterceptorTest
{

    public function testInvocationIsNotCalledWithoutProceed()
    {
        $sequence   = [];
        $advice     = $this->getAdvice($sequence); // advice will not call Invocation->proceed()
        $invocation = $this->getInvocation($sequence);

        $interceptor = new AroundInterceptor($advice);
        $result = $interceptor->invoke($invocation);

        $this->assertEquals('advice', $result, "Advice should change the return value of invocation");
        $this->assertEquals(['advice'], $sequence, "Only advice should be invoked");
    }

    public function testInvocationIsCalledWithinAdvice()
    {
        $sequence   = [];
        $advice     = function (Invocation $invocation) use (&$sequence) {
            $sequence[] = 'advice';
            $result = $invocation->proceed();
            $sequence[] = 'advice';
            return $result;
        };
        $invocation = $this->getInvocation($sequence);

        $interceptor = new AroundInterceptor($advice);
        $result = $interceptor->invoke($invocation);
        $this->assertEquals('invocation', $result, "Advice should return an original return value");
        $this->assertEquals(['advice', 'invocation', 'advice'], $sequence, "Around logic should work");
    }
}
