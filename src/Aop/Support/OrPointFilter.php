<?php
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
 * Logical "or" filter.
 */
class OrPointFilter implements PointFilter
{

    /**
     * Kind of filter
     *
     * @var int
     */
    private $kind = 0;

    /**
     * List of filters to combine
     *
     * @var PointFilter[]
     */
    private $filters;

    /**
     * Or constructor
     *
     * @param PointFilter[] $filters List of filters to combine
     */
    public function __construct(PointFilter ...$filters)
    {
        foreach ($filters as $filter) {
            $this->kind |= $filter->getKind();
        }
        $this->filters = $filters;
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
        foreach ($this->filters as $filter) {
            if ($filter->matches($point, $context)) {
                return true;
            }
        }

        return false;
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
