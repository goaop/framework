<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Go\Aop\Intercept\Joinpoint;
use PHPUnit\Framework\TestCase;

class StaticInitializationJoinpointTest extends TestCase
{
    public function testGetScopeReturnsClassNamePassedToConstructor(): void
    {
        $joinPoint = new StaticInitializationJoinpoint([], self::class);

        $this->assertSame(self::class, $joinPoint->getScope());
    }

    public function testGetThisIsAlwaysNull(): void
    {
        $joinPoint = new StaticInitializationJoinpoint([], self::class);

        $this->assertNull($joinPoint->getThis());
    }

    public function testIsDynamicIsAlwaysFalse(): void
    {
        $joinPoint = new StaticInitializationJoinpoint([], self::class);

        $this->assertFalse($joinPoint->isDynamic());
    }

    public function testInvokeWithoutScopeKeepsConstructorClassName(): void
    {
        $joinPoint = new StaticInitializationJoinpoint([], self::class);

        $joinPoint();

        $this->assertSame(self::class, $joinPoint->getScope());
    }

    public function testInvokeWithScopeOverridesClassName(): void
    {
        $joinPoint = new StaticInitializationJoinpoint([], self::class);

        $joinPoint(AbstractInvocationTest::class);

        $this->assertSame(AbstractInvocationTest::class, $joinPoint->getScope());
    }

    public function testToStringDescribesStaticInitialization(): void
    {
        $joinPoint = new StaticInitializationJoinpoint([], self::class);

        $this->assertSame(
            sprintf('staticinitialization(%s)', self::class),
            (string) $joinPoint,
        );
    }

    public function testProceedInvokesInterceptorChain(): void
    {
        $called = false;
        $advice = $this->createMock(\Go\Aop\Intercept\Interceptor::class);
        $advice->expects($this->once())
            ->method('invoke')
            ->willReturnCallback(function (Joinpoint $joinPoint) use (&$called): void {
                $called = true;
                $this->assertInstanceOf(StaticInitializationJoinpoint::class, $joinPoint);
            });

        $joinPoint = new StaticInitializationJoinpoint([$advice], self::class);
        $joinPoint();

        $this->assertTrue($called);
    }

    public function testProceedIsNoOpWithoutInterceptors(): void
    {
        $joinPoint = new StaticInitializationJoinpoint([], self::class);

        $result = $joinPoint->proceed();

        $this->assertNull($result);
    }
}
