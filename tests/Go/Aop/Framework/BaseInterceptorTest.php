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

use Go\Aop\Intercept\Invocation;
use Go\Stubs\BaseInterceptorMock;

class BaseInterceptorTest extends AbstractInterceptorTest
{
    /**
     * Concrete class name for mock, should be redefined with LSB
     */
    const INVOCATION_CLASS = 'Go\Aop\Intercept\Invocation';

    public function testReturnsRawAdvice()
    {
        $sequence = array();
        $advice   = $this->getAdvice($sequence);

        $interceptor = new BaseInterceptor($advice);
        $this->assertEquals($advice, $interceptor->getRawAdvice());
    }

    public function testCanSerializeInterceptor()
    {
        $sequence = array();
        $advice   = $this->getAdvice($sequence);
        $mock     = new BaseInterceptorMock($advice);

        $mockClass      = get_class($mock);
        $mockNameLength = strlen($mockClass);
        $result         = serialize($mock);
        $expected       = 'C:' . $mockNameLength . ':"' . $mockClass . '":161:{a:1:{s:12:"adviceMethod";a:3:{s:5:"scope";s:6:"aspect";s:6:"method";s:26:"Go\Aop\Framework\{closure}";s:6:"aspect";s:36:"Go\Aop\Framework\BaseInterceptorTest";}}}';

        $this->assertEquals($expected, $result);
    }

    public function testCanUnserializeInterceptor()
    {
        $advice = $this->getAdvice($sequence);
        $mock   = new BaseInterceptorMock($advice);

        $mockClass      = get_class($mock);
        $mockNameLength = strlen($mockClass);
        // Trick to mock unserialization of advice
        $serialized = 'C:' . $mockNameLength .':"' . $mockClass . '":161:{a:1:{s:12:"adviceMethod";a:3:{s:5:"scope";s:6:"aspect";s:6:"method";s:26:"Go\Aop\Framework\{closure}";s:6:"aspect";s:36:"Go\Aop\Framework\BaseInterceptorTest";}}}';
        $result     = unserialize($serialized);
        $this->assertEquals($advice, $result->getRawAdvice());
    }
}
 