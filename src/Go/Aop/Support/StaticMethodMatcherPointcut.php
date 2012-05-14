<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Support;

use Go\Aop\MethodMatcher;
use Go\Aop\Pointcut;
use Go\Aop\ClassFilter;
use Go\Aop\PointFilter;

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
     * @var null|ClassFilter
     */
    private $classFilter = null;

    /**
     * Set the ClassFilter to use for this pointcut.
     *
     * @param ClassFilter $classFilter
     */
    public function setClassFilter(ClassFilter $classFilter)
    {
        $this->classFilter = $classFilter;
    }

    /**
     * Return the ClassFilter for this pointcut.
     *
     * @return ClassFilter
     */
    public function getClassFilter()
    {
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
