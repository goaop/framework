<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop;

/**
 * Filter that restricts matching of a pointcut or introduction to a given set of reflection points.
 */
interface PointFilter
{

    const KIND_METHOD   = 1;
    const KIND_PROPERTY = 2;
    const KIND_CLASS    = 4;
    const KIND_TRAIT    = 8;
    const KIND_FUNCTION = 16;
    const KIND_ALL      = 31;
    const KIND_DYNAMIC  = 256;

    /**
     * Performs matching of point of code
     *
     * @param mixed $point Specific part of code, can be any Reflection class
     *
     * @return bool
     */
    public function matches($point);

    /**
     * Returns the kind of point filter
     *
     * @return integer
     */
    public function getKind();
}
