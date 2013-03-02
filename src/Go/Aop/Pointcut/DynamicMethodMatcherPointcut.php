<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Pointcut;

use Go\Aop\Pointcut;
use Go\Aop\ClassFilter;
use Go\Aop\PointFilter;
use Go\Aop\TrueClassFilter;
use Go\Aop\Support\DynamicMethodMatcher;

/**
 * Convenient superclass when we want to force subclasses to implement MethodMatcher interface,
 * but subclasses will want to be pointcuts.
 *
 * The getClassFilter() method can be overriden to customize ClassFilter behaviour as well.
 */
abstract class DynamicMethodMatcherPointcut extends DynamicMethodMatcher implements Pointcut
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
        if (!$this->classFilter) {
            $this->classFilter = TrueClassFilter::getInstance();
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
