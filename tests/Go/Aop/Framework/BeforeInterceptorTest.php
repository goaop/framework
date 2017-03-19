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

class BeforeInterceptorTest extends AbstractInterceptorTest
{
    public function testAdviceIsCalledBeforeInvocation()
    {
        $sequence   = [];
        $advice     = $this->getAdvice($sequence);
        $invocation = $this->getInvocation($sequence);

        $interceptor = new BeforeInterceptor($advice);
        $result = $interceptor->invoke($invocation);

        $this->assertEquals('invocation', $result, "Advice should not affect the return value of invocation");
        $this->assertEquals(array('advice', 'invocation'), $sequence, "Before advice should be invoked before invocation");
    }
}
