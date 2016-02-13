<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Go\Aop\Pointcut;
use Go\Aop\Support\OrPointFilter;

/**
 * Signature method pointcut checks method signature (modifiers and name) to match it
 */
class OrPointcut extends AndPointcut
{

    /**
     * Signature method matcher constructor
     *
     * @param Pointcut $first First filter
     * @param Pointcut $second Second filter
     */
    public function __construct(Pointcut $first, Pointcut $second)
    {
        $this->first  = $first;
        $this->second = $second;
        $this->kind   = $first->getKind() | $second->getKind();

        $this->classFilter = new OrPointFilter($first->getClassFilter(), $second->getClassFilter());
    }

    /**
     * Performs matching of point of code
     *
     * @param mixed $point Specific part of code, can be any Reflection class
     * @param object|string|null $instance [Optional] Instance for dynamic matching
     * @param array $arguments [Optional] Extra arguments for dynamic matching
     *
     * @return bool
     */
    public function matches($point, $instance = null, array $arguments = null)
    {
        return $this->isMatchesPointcut($point, $this->first, $instance, $arguments)
            || $this->isMatchesPointcut($point, $this->second, $instance, $arguments);
    }
}
