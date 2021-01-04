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
     */
    private int $kind;

    /**
     * Instance of filter to negate
     */
    private PointFilter $filter;

    /**
     * Not constructor
     */
    public function __construct(PointFilter $filter)
    {
        $this->kind   = $filter->getKind();
        $this->filter = $filter;
    }

    /**
     * Performs matching of point of code
     *
     * @param mixed $point Specific part of code, can be any Reflection class
     * @param null|mixed $context Related context, can be class or namespace
     * @param null|string|object $instance Invocation instance or string for static calls
     * @param null|array $arguments Dynamic arguments for method
     */
    public function matches($point, $context = null, $instance = null, array $arguments = null): bool
    {
        return !$this->filter->matches($point, $context);
    }

    /**
     * Returns the kind of point filter
     */
    public function getKind(): int
    {
        return $this->kind;
    }
}
