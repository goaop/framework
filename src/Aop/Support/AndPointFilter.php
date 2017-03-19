<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Support;

use Go\Aop\PointFilter;

/**
 * Logical "and" filter.
 */
class AndPointFilter implements PointFilter
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
     * Second part of filter
     *
     * @var PointFilter|null
     */
    private $second = null;

    /**
     * And constructor
     *
     * @param PointFilter $first First part of filter
     * @param PointFilter $second Second part of filter to match
     */
    public function __construct(PointFilter $first, PointFilter $second)
    {
        $this->kind   = $first->getKind() & $second->getKind();
        $this->first  = $first;
        $this->second = $second;
    }

    /**
     * Performs matching of point of code
     *
     * @param mixed $point Specific part of code, can be any Reflection class
     * @param null|mixed $context Related context, can be class or namespace
     * @param null|string|object $instance Invocation instance or string for static calls
     * @param null|array $arguments Dynamic arguments for method
     *
     * @return bool
     */
    public function matches($point, $context = null, $instance = null, array $arguments = null)
    {
        return $this->first->matches($point, $context) && $this->second->matches($point, $context);
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
