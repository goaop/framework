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
use Go\Lang\Attribute\After;
use Go\Lang\Attribute\Before;

/**
 * Traces private and protected methods, they are woven like the public ones
 */
class PrivateMethodAspect implements Aspect
{
    /**
     * Intercepts private and protected instance methods ("->") of the order processor
     */
    #[Before("execution(private|protected Demo\Example\OrderProcessorDemo->*(*))")]
    public function beforeInternalStep(MethodInvocation $invocation): void
    {
        echo 'Calling Before Interceptor for ',
        $invocation,
        ' with arguments: ',
        json_encode($invocation->getArguments()),
        PHP_EOL;
    }

    /**
     * Intercepts private static methods ("::") of the order processor
     */
    #[After("execution(private Demo\Example\OrderProcessorDemo::*(*))")]
    public function afterStaticStep(MethodInvocation $invocation): void
    {
        echo 'Calling After Interceptor for ', $invocation, PHP_EOL;
    }
}
