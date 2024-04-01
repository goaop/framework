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

use Go\Aop\AdviceAround;
use Go\Aop\Intercept\Joinpoint;

/**
 * "Around" interceptor
 *
 * @api
 */
final class AroundInterceptor extends AbstractInterceptor implements AdviceAround
{
    public function invoke(Joinpoint $joinpoint): mixed
    {
        return ($this->adviceMethod)($joinpoint);
    }
}
