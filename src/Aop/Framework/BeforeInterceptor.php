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

use Go\Aop\AdviceTypeEnum;
use Go\Aop\Intercept\Joinpoint;
use Go\Core\LazyServiceCreation;

/**
 * "Before" interceptor
 *
 * @api
 */
final class BeforeInterceptor extends AbstractInterceptor
{
    public function invoke(Joinpoint $joinpoint): mixed
    {
        if (LazyServiceCreation::$inProgress !== []) {
            $this->assertAspectIsCreated($joinpoint);
        }
        ($this->adviceMethod)($joinpoint);

        return $joinpoint->proceed();
    }

    public function getType(): AdviceTypeEnum
    {
        return AdviceTypeEnum::Before;
    }
}
