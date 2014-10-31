<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Exception;
use Go\Aop\AdviceAround;
use Go\Aop\Intercept\Joinpoint;

/**
 * "Around" interceptor
 */
class AroundInterceptor extends BaseInterceptor implements AdviceAround
{
    /**
     * Around invoker
     *
     * @param Joinpoint $joinpoint the concrete joinpoint
     *
     * @return mixed the result of the call to {@link Joinpoint::proceed()}
     * @throws Exception
     */
    final public function invoke(Joinpoint $joinpoint)
    {
        $adviceMethod = $this->adviceMethod;

        return $adviceMethod($joinpoint);
    }
}
