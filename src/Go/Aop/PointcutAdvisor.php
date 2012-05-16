<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
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
