<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Support;

use Go\Aop\PointFilter;

/**
 * Logical "not" filter.
 */
class NotPointFilter implements PointFilter
{
    /**
     * Kind of filter
     *
     * @var int
     */
    private $kind = 0;

    /**
     * First part of filter
     *
     * @var PointFilter|null
     */
    private $first = null;

    /**
     * Not constructor
     *
     * @param PointFilter $first First part of filter
     */
    public function __construct(PointFilter $first)
    {
        $this->kind   = $first->getKind();
        $this->first  = $first;
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
        return !$this->first->matches($point);
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
}
