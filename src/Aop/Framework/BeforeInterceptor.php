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

use Go\Aop\AdviceBefore;
use Go\Aop\Intercept\Joinpoint;

/**
 * "Before" interceptor
 *
 * @api
 */
final class BeforeInterceptor extends BaseInterceptor implements AdviceBefore
{
    /**
     * Before invoker for joinpoint
     *
     * @param Joinpoint $joinpoint the concrete joinpoint
     *
     * @return mixed the result of the call to {@link Joinpoint::proceed()},
     */
    public function invoke(Joinpoint $joinpoint)
    {
        $adviceMethod = $this->adviceMethod;
        $adviceMethod($joinpoint);

        return $joinpoint->proceed();
    }
}
