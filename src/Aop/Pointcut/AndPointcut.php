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
use Go\Aop\Support\AndPointFilter;

/**
 * Signature method pointcut checks method signature (modifiers and name) to match it
 */
class AndPointcut implements Pointcut
{

    use PointcutClassFilterTrait;

    /**
     * @var Pointcut
     */
    protected $first;

    /**
     * @var Pointcut
     */
    protected $second;

    /**
     * Returns pointcut kind
     *
     * @var int
     */
    protected $kind = 0;

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
        $this->kind   = $first->getKind() & $second->getKind();

        $this->classFilter = new AndPointFilter($first->getClassFilter(), $second->getClassFilter());
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
            && $this->isMatchesPointcut($point, $this->second, $instance, $arguments);
    }

    /**
     * Returns the kind of point filter
     *
     * @return integer
     */
    public function getKind()
    {
        return $this->kind;
    }

    /**
     * Checks if point filter matches the point
     *
     * @param \ReflectionMethod|\ReflectionProperty $point
     * @param Pointcut $pointcut Pointcut part
     * @param object|string|null $instance [Optional] Instance for dynamic matching
     * @param array $arguments [Optional] Extra arguments for dynamic matching
     *
     * @return bool
     */
    protected function isMatchesPointcut($point, Pointcut $pointcut, $instance = null, array $arguments = null)
    {
        $preFilter = method_exists($point, 'getDeclaringClass')
            ? $point->getDeclaringClass()
            : $point->getNamespaceName();

        return $pointcut->matches($point, $instance, $arguments)
            && $pointcut->getClassFilter()->matches($preFilter);
    }
}
