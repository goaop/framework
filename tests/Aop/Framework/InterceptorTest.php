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

        $this->assertSame(10, $interceptor->getAdviceOrder());
    }

    public function testCreatesAfterInterceptor(): void
    {
        $interceptor = Interceptor::after(static function (Joinpoint $joinpoint): void {});

        $this->assertSame(0, $interceptor->getAdviceOrder());
    }

    public function testCreatesAroundInterceptor(): void
    {
        $advice      = static fn(Joinpoint $joinpoint): mixed => $joinpoint->proceed();
        $interceptor = Interceptor::around($advice);

        $this->assertSame($advice, $interceptor->getRawAdvice());
    }

    public function testCreatesAfterThrowingInterceptor(): void
    {
        $advice      = static function (Joinpoint $joinpoint, \Throwable $throwable): void {};
        $interceptor = Interceptor::afterThrowing($advice);

        $this->assertSame($advice, $interceptor->getRawAdvice());
    }
}
