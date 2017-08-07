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

namespace Go\Aop\Support;

use Go\Aop\Advice;
use Go\Aop\Framework\DynamicInvocationMatcherInterceptor;
use Go\Aop\Pointcut;
use Go\Aop\PointcutAdvisor;
use Go\Aop\PointFilter;

/**
 * Convenient Pointcut-driven Advisor implementation.
 *
 * This is the most commonly used Advisor implementation. It can be used with any pointcut and advice type,
 * except for introductions. There is normally no need to subclass this class, or to implement custom Advisors.
 */
class DefaultPointcutAdvisor extends AbstractGenericAdvisor implements PointcutAdvisor
{

    /**
     * Pointcut instance
     *
     * @var Pointcut
     */
    private $pointcut;

    /**
     * Create a DefaultPointcutAdvisor, specifying Pointcut and Advice.
     *
     * @param Pointcut $pointcut The Pointcut targeting the Advice
     * @param Advice $advice The Advice to run when Pointcut matches
     */
    public function __construct(Pointcut $pointcut, Advice $advice)
    {
        $this->pointcut = $pointcut;
        parent::__construct($advice);
    }

    /**
     * {@inheritdoc}
     */
    public function getAdvice() : Advice
    {
        $advice = parent::getAdvice();
        if ($this->pointcut->getKind() & PointFilter::KIND_DYNAMIC) {
            $advice = new DynamicInvocationMatcherInterceptor(
                $this->pointcut,
                $advice
            );
        }

        return $advice;
    }

    /**
     * Get the Pointcut that drives this advisor.
     */
    public function getPointcut() : Pointcut
    {
        return $this->pointcut;
    }
}
