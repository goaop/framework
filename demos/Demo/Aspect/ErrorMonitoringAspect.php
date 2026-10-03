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

namespace Demo\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\AfterThrowing;
use Throwable;

/**
 * Error monitoring aspect reports every exception thrown by the payment gateway
 */
class ErrorMonitoringAspect implements Aspect
{
    /**
     * This advice runs only when the method throws
     *
     * It receives the exception as the second argument. The exception is rethrown after the advice,
     * so the caller still handles it: the advice only observes the failure (logging, metrics, alerts).
     */
    #[AfterThrowing("execution(public Demo\Example\PaymentDemo->*(*))")]
    public function afterThrowing(MethodInvocation $invocation, Throwable $exception): void
    {
        echo 'Calling AfterThrowing Interceptor for ',
        $invocation,
        ', reporting ',
        $exception::class,
        ': ',
        $exception->getMessage(),
        PHP_EOL;
    }
}
