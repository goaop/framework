<?php

declare(strict_types=1);

namespace Go\Aop\Framework;

use Go\Aop\Intercept\Joinpoint;
use PHPUnit\Framework\TestCase;

final class InterceptorTest extends TestCase
{
    public function testCreatesBeforeInterceptor(): void
    {
        $interceptor = Interceptor::before(static function (Joinpoint $joinpoint): void {}, order: 10);

        $this->assertInstanceOf(BeforeInterceptor::class, $interceptor);
        $this->assertSame(10, $interceptor->getAdviceOrder());
    }

    public function testCreatesAfterInterceptor(): void
    {
        $interceptor = Interceptor::after(static function (Joinpoint $joinpoint): void {});

        $this->assertInstanceOf(AfterInterceptor::class, $interceptor);
        $this->assertSame(0, $interceptor->getAdviceOrder());
    }

    public function testCreatesAroundInterceptor(): void
    {
        $interceptor = Interceptor::around(static fn(Joinpoint $joinpoint): mixed => $joinpoint->proceed());

        $this->assertInstanceOf(AroundInterceptor::class, $interceptor);
    }

    public function testCreatesAfterThrowingInterceptor(): void
    {
        $interceptor = Interceptor::afterThrowing(static function (Joinpoint $joinpoint, \Throwable $throwable): void {});

        $this->assertInstanceOf(AfterThrowingInterceptor::class, $interceptor);
    }
}
