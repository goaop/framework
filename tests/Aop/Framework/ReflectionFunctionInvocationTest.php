<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Go\Aop\Intercept\FunctionInvocation;
use Go\Aop\Intercept\Interceptor;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ReflectionFunctionInvocation — all paths use first-class callables.
 */
#[AllowMockObjectsWithoutExpectations]
class ReflectionFunctionInvocationTest extends TestCase
{
    /**
     * Basic invocation via first-class callable (no advice).
     * The callable `\strlen(...)` must be called directly without reflection.
     */
    public function testInvokeCallsFirstClassCallableDirectly(): void
    {
        $invocation = new ReflectionFunctionInvocation([], 'strlen', \strlen(...));
        $result     = $invocation(['hello']);
        $this->assertSame(5, $result);
    }

    /**
     * With a first-class callable, proceed() calls the callable directly, bypassing reflection.
     */
    public function testInvokeWithFirstClassCallableCallsItDirectly(): void
    {
        $invocation = new ReflectionFunctionInvocation([], 'strlen', \strlen(...));
        $result     = $invocation(['world']);
        $this->assertSame(5, $result);
    }

    /**
     * The first-class callable path must still route through advice interceptors.
     */
    public function testAdviceIsCalledBeforeProceedingWithCallable(): void
    {
        $called = false;
        $advice = $this->createMock(Interceptor::class);
        $advice->expects($this->once())
            ->method('invoke')
            ->willReturnCallback(function (FunctionInvocation $inv) use (&$called): mixed {
                $called = true;

                return $inv->proceed();
            });

        $invocation = new ReflectionFunctionInvocation([$advice], 'strlen', \strlen(...));
        $result     = $invocation(['phpunit']);

        $this->assertTrue($called);
        $this->assertSame(7, $result);
    }

    /**
     * Verify that the callable passed to the joinpoint is actually invoked and not the proxy.
     * This mirrors the function proxy pattern where \file_get_contents(...) (global function)
     * is passed to avoid calling the namespace-scoped proxy recursively.
     */
    public function testCallableReceivesCorrectArguments(): void
    {
        $callable = static function (string $a, string $b): string {
            return $a . $b;
        };

        $invocation = new ReflectionFunctionInvocation([], 'strlen', $callable);
        $result     = $invocation(['foo', 'bar']);

        $this->assertSame('foobar', $result);
    }
}
