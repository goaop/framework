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

use Go\Aop\AdviceAfter;
use Go\Aop\Intercept\Joinpoint;
use Throwable;

/**
 * "After Throwing" interceptor
 *
 * @api
 */
final class AfterThrowingInterceptor extends AbstractInterceptor implements AdviceAfter
{
    /**
     * @inheritdoc
     * @throws Throwable if original joinpoint throws an exception
     */
    public function invoke(Joinpoint $joinpoint): mixed
    {
        try {
            return $joinpoint->proceed();
        } catch (Throwable $throwableInstance) {
            ($this->adviceMethod)($joinpoint, $throwableInstance);

            throw $throwableInstance;
        }
    }
}
