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

class AfterInterceptorTest extends AbstractInterceptorTest
{

    public function testAdviceIsCalledAfterInvocation()
    {
        $sequence   = [];
        $advice     = $this->getAdvice($sequence);
        $invocation = $this->getInvocation($sequence);

        $interceptor = new AfterInterceptor($advice);
        $result = $interceptor->invoke($invocation);

        $this->assertEquals('invocation', $result, "Advice should not affect the return value of invocation");
        $this->assertEquals(['invocation', 'advice'], $sequence, "After advice should be invoked after invocation");
    }

    public function testAdviceIsCalledAfterExceptionInInvocation()
    {
        $sequence   = [];
        $advice     = $this->getAdvice($sequence);
        $invocation = $this->getInvocation($sequence, true);

        $interceptor = new AfterInterceptor($advice);
        $this->expectException('RuntimeException');
        try {
            $interceptor->invoke($invocation);
        } catch (\Exception $e) {
            $this->assertEquals(['invocation', 'advice'], $sequence, "After advice should be invoked after invocation");
            throw $e;
        }
    }
}
