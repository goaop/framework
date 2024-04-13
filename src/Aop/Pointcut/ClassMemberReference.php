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

use Go\Aop\Pointcut;

/**
 * Readonly data transfer object for storing a reference to the class member (property or method)
 */
final readonly class ClassMemberReference
{
    /**
     * Default constructor
     *
     * @param Pointcut         $classFilter Filter for class names
     * @param ModifierPointcut $visibilityFilter Member visibility filter (public/protected/etc)
     * @param ModifierPointcut $accessTypeFilter Filter for access type (statically "::" or dynamically "->")
     * @param string           $memberNamePattern Expression for the name
     */
    public function __construct(
        public Pointcut         $classFilter,
        public ModifierPointcut $visibilityFilter,
        public ModifierPointcut $accessTypeFilter,
        public string           $memberNamePattern
    ) {}
}
