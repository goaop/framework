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
        return $this->advice;
    }

    public function getPointcut(): Pointcut
    {
        return $this->pointcut;
    }
}
