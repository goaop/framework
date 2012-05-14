<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop;

use Reflector;

/**
 * Filter that restricts matching of a pointcut or introduction to a given set of reflection points.
 */
interface PointFilter
{
    /**
     * Performs matching of point of code
     *
     * @param Reflector $point Specific part of code, can be any Reflection class
     *
     * @return bool
     */
    public function matches(Reflector $point);
}
