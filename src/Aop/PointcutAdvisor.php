<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop;

/**
 * Super-interface for all Advisors that are driven by a pointcut.
 */
interface PointcutAdvisor extends Advisor
{
    /**
     * Gets the Pointcut that drives this advisor.
     */
    public function getPointcut(): Pointcut;
}
