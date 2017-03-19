<?php
declare(strict_types = 1);
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
 *
 * This covers nearly all advisors except introduction advisors, for which method-level matching doesn't apply.
 */
interface PointcutAdvisor extends Advisor
{
    /**
     * Get the Pointcut that drives this advisor.
     *
     * @return Pointcut The pointcut
     */
    public function getPointcut();
}
