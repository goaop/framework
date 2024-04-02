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
 * Readonly data transfer object for storing a reference to the class member (property or method)
 */
readonly class ClassMemberReference
{
    /**
     * Default constructor
     *
     * @param PointFilter           $classFilter       Filter for class names
     * @param ModifierMatcherFilter $visibilityFilter  Member visibility filter (public/protected/etc)
     * @param ModifierMatcherFilter $accessTypeFilter  Filter for access type (statically "::" or dynamically "->")
     * @param string                $memberNamePattern Expression for the name
     */
    public function __construct(
        public PointFilter           $classFilter,
        public ModifierMatcherFilter $visibilityFilter,
        public ModifierMatcherFilter $accessTypeFilter,
        public string                $memberNamePattern
    ) {}
}
