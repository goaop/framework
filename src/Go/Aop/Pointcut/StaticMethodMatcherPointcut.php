<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Pointcut;

use Go\Aop\Pointcut;
use Go\Aop\PointFilter;
use Go\Aop\Support\TruePointFilter;
use Go\Aop\Support\StaticMethodMatcher;

/**
 * Convenient abstract superclass for static method matchers, which don't care about arguments at runtime.
 *
 * The "classFilter" property can be set to customize ClassFilter behavior.
 */
abstract class StaticMethodMatcherPointcut extends StaticMethodMatcher implements Pointcut
{

    /**
     * Filter for class
     *
     * @var null|PointFilter
     */
    private $classFilter = null;

    /**
     * Set the ClassFilter to use for this pointcut.
     *
     * @param PointFilter $classFilter
     */
    public function setClassFilter(PointFilter $classFilter)
    {
        $this->classFilter = $classFilter;
    }

    /**
     * Return the class filter for this pointcut.
     *
     * @return PointFilter
     */
    public function getClassFilter()
    {
        if (!$this->classFilter) {
            $this->classFilter = TruePointFilter::getInstance();
        }
        return $this->classFilter;
    }

    /**
     * Return the PointFilter for this pointcut.
     *
     * @return PointFilter
     */
    public function getPointFilter()
    {
        return $this;
    }
}
