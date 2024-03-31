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

use Closure;
use Go\Aop\Intercept\Invocation;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

abstract class AbstractInterceptorTestCase extends TestCase
{
    /**
     * Concrete class name for mock, should be redefined with LSB
     */
    protected const INVOCATION_CLASS = Invocation::class;

    /**
     * Returns a test advice that writes itself to the sequence
     *
     * @param array $sequenceRecorder
     */
    protected function getAdvice(array &$sequenceRecorder): Closure
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
     * @return MockObject|Invocation
     */
    protected function getInvocation(array &$sequenceRecorder, bool $throwException = false): Invocation
    {
        $invocation = $this->createMock(static::INVOCATION_CLASS);
        $invocation
            ->expects($this->any())
            ->method('proceed')
            ->will(
                $this->returnCallback(
                    function () use (&$sequenceRecorder, $throwException) {
                        $sequenceRecorder[] = 'invocation';
                        if ($throwException) {
                            throw new RuntimeException('Expected exception');
                        }
                        return 'invocation';
                    }
                )
            );

        return $invocation;
    }
}
