<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2013, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Pointcut;

use Go\Aop\Pointcut;
use Go\Aop\PointFilter;
use Go\Aop\Support\TruePointFilter;

/**
 * Signature method pointcut checks method signature (modifiers and name) to match it
 */
class NotPointcut implements PointFilter, Pointcut
{
    /**
     * @var Pointcut
     */
    protected $pointcut;

    /**
     * Kind of pointcut
     *
     * @var int
     */
    protected $kind = 0;

    /**
     * Inverse pointcut matcher
     *
     * @param Pointcut $pointcut Pointcut expression
     */
    public function __construct(Pointcut $pointcut)
    {
        $this->pointcut = $pointcut;
        $this->kind     = $pointcut->getPointFilter()->getKind();
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
        $isMatchesClass = $this->pointcut->getClassFilter()->matches($point->getDeclaringClass());
        if (!$isMatchesClass) {
            return true;
        }
        $isMatchesPoint = $this->pointcut->getPointFilter()->matches($point);
        if (!$isMatchesPoint) {
            return true;
        }
        return false;
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
     * Return the class filter for this pointcut.
     *
     * @return PointFilter
     */
    public function getClassFilter()
    {
        return TruePointFilter::getInstance();
    }

    /**
     * Return the PointFilter for this pointcut.
     *
     * This can be method filter, property filter.
     *
     * @return PointFilter
     */
    public function getPointFilter()
    {
        return $this;
    }
}
