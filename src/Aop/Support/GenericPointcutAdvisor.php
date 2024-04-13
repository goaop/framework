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
use Go\Aop\Intercept\Interceptor;
use Go\Aop\Pointcut;
use Go\Aop\PointcutAdvisor;

/**
 * Convenient Pointcut-driven Advisor implementation.
 *
 * This is the most commonly used Advisor implementation. It can be used with any pointcut and advice type,
 * including introductions.
 */
final readonly class GenericPointcutAdvisor implements PointcutAdvisor
{
    public function __construct(private Pointcut $pointcut, private Advice $advice) {}

    public function getAdvice(): Advice
    {
        // For dynamic pointcuts, we use special dynamic invocation matcher interceptor
        // This part can't be moved to the constructor, as it breaks lazy-evaluation for PointcutReference
        if (($this->advice instanceof Interceptor) && ($this->pointcut->getKind() & Pointcut::KIND_DYNAMIC)) {
            $advice = new DynamicInvocationMatcherInterceptor(
                $this->pointcut,
                $this->advice
            );
        } else {
            $advice = $this->advice;
        }

        return $advice;
    }

    public function getPointcut(): Pointcut
    {
        return $this->pointcut;
    }
}
