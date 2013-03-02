<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2013, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Pointcut;

use Go\Aop\PointFilter;

/**
 * Signature method pointcut checks method signature (modifiers and name) to match it
 */
class NotPointcut extends StaticMethodMatcherPointcut
{

    /**
     * @var PointFilter
     */
    protected $first;

    /**
     * Signature method matcher constructor
     *
     * @param PointFilter $first First filter
     */
    public function __construct(PointFilter $first)
    {
        $this->first  = $first;
    }

    /**
     * Performs matching of point of code
     *
     * @param mixed $method Specific part of code, can be any Reflection class
     *
     * @return bool
     */
    public function matches($method)
    {
        return !$this->first->matches($method);
    }
}
