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
use PHPUnit_Framework_TestCase as TestCase;

abstract class AbstractInterceptorTest extends TestCase
{
    /**
     * Concrete class name for mock, should be redefined with LSB
     */
    const INVOCATION_CLASS = 'Go\Aop\Intercept\Invocation';

    /**
     * Returns a test advice that writes itself to the sequence
     *
     * @param array $sequenceRecorder
     * @return callable
     */
    protected function getAdvice(&$sequenceRecorder)
    {
        return function () use (&$sequenceRecorder) {
            $sequenceRecorder[] = 'advice';
            return 'advice';
        };
    }

    /**
     * Returns an empty invocation that can update the sequence on invocation
     *
     * @param array $sequenceRecorder
     * @return \PHPUnit_Framework_MockObject_MockObject|Invocation
     */
    protected function getInvocation(&$sequenceRecorder, $throwException = false)
    {
        $invocation = $this->getMock(static::INVOCATION_CLASS);
        $invocation
            ->expects($this->any())
            ->method('proceed')
            ->will(
                $this->returnCallback(
                    function () use (&$sequenceRecorder, $throwException) {
                        $sequenceRecorder[] = 'invocation';
                        if ($throwException) {
                            throw new \RuntimeException('Expected exception');
                        }
                        return 'invocation';
                    }
                )
            );

        return $invocation;
    }
}
 