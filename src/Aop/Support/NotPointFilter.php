<?php
declare(strict_types = 1);
/*
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
     * @param null|mixed $context Related context, can be class or namespace
     * @param null|string|object $instance Invocation instance or string for static calls
     * @param null|array $arguments Dynamic arguments for method
     *
     * @return bool
     */
    public function matches($point, $context = null, $instance = null, array $arguments = null)
    {
        return !$this->first->matches($point, $context);
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
