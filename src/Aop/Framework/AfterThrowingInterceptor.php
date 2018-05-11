<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Exception;
use Go\Aop\AdviceAfter;
use Go\Aop\Intercept\Joinpoint;

/**
 * "After Throwing" interceptor
 *
 * @api
 */
final class AfterThrowingInterceptor extends BaseInterceptor implements AdviceAfter
{
    /**
     * @inheritdoc
     * @throws Exception
     */
    public function invoke(Joinpoint $joinpoint)
    {
        try {
            return $joinpoint->proceed();
        } catch (Exception $invocationException) {
            ($this->adviceMethod)($joinpoint, $invocationException);

            throw $invocationException;
        }
    }
}
