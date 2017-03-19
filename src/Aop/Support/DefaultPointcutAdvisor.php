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
use Go\Aop\PointFilter;

/**
 * Convenient Pointcut-driven Advisor implementation.
 *
 * This is the most commonly used Advisor implementation. It can be used with any pointcut and advice type,
 * except for introductions. There is normally no need to subclass this class, or to implement custom Advisors.
 */
class DefaultPointcutAdvisor extends AbstractGenericPointcutAdvisor
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
     *
     * @return Pointcut The pointcut
     */
    public function getPointcut()
    {
        return $this->pointcut;
    }

    /**
     * Specify the pointcut targeting the advice.
     *
     * @param Pointcut $pointcut The Pointcut targeting the Advice
     */
    public function setPointcut(Pointcut $pointcut)
    {
        $this->pointcut = $pointcut;
    }

    /**
     * Return string representation of object
     *
     * @return string
     */
    public function __toString()
    {
        $pointcutClass = get_class($this->getPointcut());
        $adviceClass   = get_class($this->getAdvice());

        return get_called_class() . ": pointcut [{$pointcutClass}]; advice [{$adviceClass}]";
    }
}
