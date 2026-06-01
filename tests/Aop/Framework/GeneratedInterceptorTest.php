<?php

declare(strict_types=1);

namespace Go\Aop\Framework;

use Go\Aop\AspectException;
use Go\Aop\Intercept\Joinpoint;
use PHPUnit\Framework\TestCase;

final class GeneratedInterceptorTest extends TestCase
{
    public function testRejectsNonAspectCallableAdvice(): void
    {
        $this->expectException(AspectException::class);
        $this->expectExceptionMessage('unsupported non-aspect callable');

        GeneratedInterceptor::fromAdvice(
            'manual-advisor',
            new BeforeInterceptor(static function (Joinpoint $joinpoint): void {})
        );
    }
}
