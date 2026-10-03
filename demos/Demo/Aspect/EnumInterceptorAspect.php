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

use Demo\Example\OrderStatus;
use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Around;
use Go\Lang\Attribute\Before;

/**
 * Intercepts the methods of an enum: the cases stay the same, only the methods are woven
 */
class EnumInterceptorAspect implements Aspect
{
    /**
     * Decorates the label of every case: an enum case is the "$this" of the invocation
     *
     * @param MethodInvocation<OrderStatus> $invocation
     */
    #[Around("execution(public Demo\Example\OrderStatus->label(*))")]
    public function aroundLabel(MethodInvocation $invocation): string
    {
        $label = $invocation->proceed();
        echo 'Calling Around Interceptor for ', $invocation, ', label: ', json_encode($label), PHP_EOL;

        return "{$label} [{$invocation->getThis()->name}]";
    }

    /**
     * Static enum methods are intercepted as well
     */
    #[Before("execution(public Demo\Example\OrderStatus::next(*))")]
    public function beforeNext(MethodInvocation $invocation): void
    {
        echo 'Calling Before Interceptor for ', $invocation, PHP_EOL;
    }
}
