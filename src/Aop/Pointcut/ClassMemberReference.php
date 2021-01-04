<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Go\Aop\PointFilter;
use Go\Aop\Support\ModifierMatcherFilter;

/**
 * Data transfer object for storing a reference to the class member (property or method)
 */
class ClassMemberReference
{
    /**
     * Filter for class names
     */
    private PointFilter $classFilter;

    /**
     * Member visibility filter (public/protected/etc)
     */
    private ModifierMatcherFilter $visibilityFilter;

    /**
     * Filter for access type (statically "::" or dynamically "->")
     */
    private ModifierMatcherFilter $accessTypeFilter;

    /**
     * Member name pattern
     */
    private string $memberNamePattern;

    /**
     * Default constructor
     *
     * @param PointFilter           $classFilter
     * @param ModifierMatcherFilter $visibilityFilter  Public/protected/etc
     * @param ModifierMatcherFilter $accessTypeFilter  Static or dynamic
     * @param string                $memberNamePattern Expression for the name
     */
    public function __construct(
        PointFilter $classFilter,
        ModifierMatcherFilter $visibilityFilter,
        ModifierMatcherFilter $accessTypeFilter,
        string $memberNamePattern
    ) {
        $this->classFilter       = $classFilter;
        $this->visibilityFilter  = $visibilityFilter;
        $this->accessTypeFilter  = $accessTypeFilter;
        $this->memberNamePattern = $memberNamePattern;
    }

    /**
     * Returns the filter for class
     */
    public function getClassFilter(): PointFilter
    {
        return $this->classFilter;
    }

    /**
     * Returns the filter for visibility: public/protected/private
     */
    public function getVisibilityFilter(): ModifierMatcherFilter
    {
        return $this->visibilityFilter;
    }

    /**
     * Returns the filter for access type: static/dynamic
     */
    public function getAccessTypeFilter(): ModifierMatcherFilter
    {
        return $this->accessTypeFilter;
    }

    /**
     * Returns the pattern for member name
     */
    public function getMemberNamePattern(): string
    {
        return $this->memberNamePattern;
    }
}
