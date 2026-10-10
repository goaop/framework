<?php

declare(strict_types=1);

namespace Go\Stubs\ZEngine;

use Go\Aop\Aspect;

/**
 * An aspect the MatcherErrorAdvisor pointcut fails on: aspects are never woven, so its load must not fail
 */
final class MatcherErrorAspect implements Aspect
{
    public function helper(): string
    {
        return 'helper';
    }
}
