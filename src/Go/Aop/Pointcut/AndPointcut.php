<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Go\Aop\Pointcut;
use Go\Aop\PointFilter;
use Go\Aop\Support\AndPointFilter;

/**
 * Signature method pointcut checks method signature (modifiers and name) to match it
 */
class AndPointcut implements Pointcut
{

    /**
     * @var Pointcut
     */
    protected $first;

    /**
     * @var Pointcut
     */
    protected $second;

    /**
     * Combined class filter
     *
     * @var PointFilter|null
     */
    protected $classFilter = null;

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
     *
     * @return bool
     */
    public function matches($point)
    {
        return $this->isMatchesPointcut($point, $this->first) && $this->isMatchesPointcut($point, $this->second);
    }

    /**
     * Return the class filter for this pointcut.
     *
     * @return PointFilter
     */
    public function getClassFilter()
    {
        return $this->classFilter;
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
     *
     * @return bool
     */
    protected function isMatchesPointcut($point, Pointcut $pointcut)
    {
        $preFilter = method_exists($point, 'getDeclaringClass')
            ? $point->getDeclaringClass()
            : $point->getNamespaceName();

        return $pointcut->matches($point)
            && $pointcut->getClassFilter()->matches($preFilter);
    }
}
