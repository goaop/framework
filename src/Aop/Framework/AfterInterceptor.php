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

use Go\Aop\AdviceAfter;
use Go\Aop\Intercept\Joinpoint;

/**
 * "After" interceptor
 *
 * @api
 */
final class AfterInterceptor extends BaseInterceptor implements AdviceAfter
{
    /**
     * After invoker
     *
     * @param Joinpoint $joinpoint the concrete joinpoint
     *
     * @return mixed the result of the call to {@link Joinpoint::proceed()}
     */
    public function invoke(Joinpoint $joinpoint)
    {
        try {
            $result = $joinpoint->proceed();
        } finally {
            $adviceMethod = $this->adviceMethod;
            $adviceMethod($joinpoint);
        }

        return $result;
    }
}
