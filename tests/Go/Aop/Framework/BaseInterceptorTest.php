<?php

declare(strict_types = 1);
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
use Go\Stubs\AbstractInterceptorMock;

class BaseInterceptorTest extends AbstractInterceptorTestCase
{
    /**
     * Concrete class name for mock, should be redefined with LSB
     */
    protected const INVOCATION_CLASS = Invocation::class;

    public function testReturnsRawAdvice()
    {
        $sequence = [];
        $advice   = $this->getAdvice($sequence);

        $interceptor = $this->getMockForAbstractClass(
            AbstractInterceptor::class,
            [$advice]
        );
        $this->assertEquals($advice, $interceptor->getRawAdvice());
    }

    public function testCanSerializeInterceptor()
    {
        $sequence = [];
        $advice   = $this->getAdvice($sequence);
        $mock     = new AbstractInterceptorMock($advice);

        $mockClass      = get_class($mock);
        $mockNameLength = strlen($mockClass);
        $result         = serialize($mock);
        $expected       = 'O:' . $mockNameLength . ':"' . $mockClass . '":1:{s:12:"adviceMethod";a:2:{s:4:"name";s:26:"Go\Aop\Framework\{closure}";s:5:"class";s:44:"Go\Aop\Framework\AbstractInterceptorTestCase";}}';

        $this->assertEquals($expected, $result);
    }

    public function testCanUnserializeInterceptor()
    {
        $sequence = [];
        $advice   = $this->getAdvice($sequence);
        $mock     = new AbstractInterceptorMock($advice);

        $mockClass      = get_class($mock);
        $mockNameLength = strlen($mockClass);
        // Trick to mock unserialization of advice
        $serialized = 'O:' . $mockNameLength .':"' . $mockClass . '":1:{s:12:"adviceMethod";a:3:{s:5:"scope";s:6:"aspect";s:6:"method";s:26:"Go\Aop\Framework\{closure}";s:6:"aspect";s:36:"Go\Aop\Framework\BaseInterceptorTest";}}';
        $result     = unserialize($serialized);
        $this->assertEquals($advice, $result->getRawAdvice());
    }
}
