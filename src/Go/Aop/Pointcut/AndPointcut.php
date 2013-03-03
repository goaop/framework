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
class AndPointcut extends StaticMethodMatcherPointcut
{

    /**
     * @var PointFilter
     */
    protected $first;

    /**
     * @var PointFilter
     */
    protected $second;

    /**
     * Signature method matcher constructor
     *
     * @param PointFilter $first First filter
     * @param PointFilter $second Second filter
     */
    public function __construct(PointFilter $first, PointFilter $second)
    {
        $this->first  = $first;
        $this->second = $second;
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
        return $this->first->matches($method) && $this->second->matches($method);
    }
}
