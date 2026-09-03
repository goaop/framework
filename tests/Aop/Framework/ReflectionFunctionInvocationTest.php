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

    /**
     * Verifies that by-reference arguments are correctly forwarded through the invocation chain.
     *
     * The proxy passes arguments as `[&$var]`, so `$this->arguments[0]` is a PHP reference.
     * Unpacking a reference-bearing array with `...$args` preserves the reference binding,
     * meaning the callable can modify the original caller's variable.
     */
    public function testPassByReferenceIsForwarded(): void
    {
        $invocation = new ReflectionFunctionInvocation([], 'preg_match', \preg_match(...));

        $matches = null;
        $invocation(['/(\d+)/', 'abc123', &$matches]);

        // @phpstan-ignore method.impossibleType ($matches is filled by reference inside the invocation)
        $this->assertSame(['123', '123'], $matches);
    }

    /**
     * getFunction() exposes the underlying ReflectionFunction instance.
     */
    public function testGetFunctionReturnsReflectionFunction(): void
    {
        $invocation = new ReflectionFunctionInvocation([], 'strlen', \strlen(...));

        $this->assertSame('strlen', $invocation->getFunction()->getName());
    }

    /**
     * __toString() produces a friendly `execution(functionName())` description.
     */
    public function testToStringDescribesFunctionExecution(): void
    {
        $invocation = new ReflectionFunctionInvocation([], 'strlen', \strlen(...));

        $this->assertSame('execution(strlen())', (string) $invocation);
    }

    /**
     * Recursive invocations (the callable calling the same joinpoint again) must push
     * the current arguments/cursor onto a stack and restore them once the nested call
     * unwinds, so the outer call resumes with its own arguments intact.
     */
    public function testRecursiveInvocationPreservesOuterStackFrame(): void
    {
        $invocation = null;
        $callable   = static function (int $n) use (&$invocation): int {
            if ($n <= 0) {
                return 0;
            }

            /** @var ReflectionFunctionInvocation $invocation */
            $nested = $invocation([$n - 1]);
            // @phpstan-ignore cast.int (FunctionInvocation's generic V is unresolved here; the wrapped callable is declared `: int`)
            return $n + (int) $nested;
        };
        $invocation = new ReflectionFunctionInvocation([], 'strlen', $callable);

        $result = $invocation([3]);

        $this->assertSame(6, $result);
        // Outer call's arguments must be restored after the nested recursive calls unwind.
        $this->assertSame([3], $invocation->getArguments());
    }
}
