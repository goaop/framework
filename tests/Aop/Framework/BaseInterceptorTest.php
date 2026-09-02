<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Go\Aop\Intercept\Invocation;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class BaseInterceptorTest extends AbstractInterceptorTestCase
{
    /**
     * Concrete class name for mock, should be redefined with LSB
     */
    protected const INVOCATION_CLASS = Invocation::class;

    public function testReturnsRawAdvice(): void
    {
        $sequence = [];
        $advice   = $this->getAdvice($sequence);

        $interceptor = $this->getMockBuilder(AbstractInterceptor::class)
            ->setConstructorArgs([$advice])
            ->onlyMethods(['invoke', 'getType'])
            ->getMock();
        $this->assertEquals($advice, $interceptor->getRawAdvice());
    }
}
