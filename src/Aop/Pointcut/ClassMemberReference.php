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
     */
    public function __construct(
        /** Filter for class names */
        public Pointcut $classFilter,
        /** Member visibility filter (public/protected/etc) */
        public ModifierPointcut $visibilityFilter,
        /** Filter for access type (statically "::" or dynamically "->") */
        public ModifierPointcut $accessTypeFilter,
        /** Expression for the name */
        public string $memberNamePattern,
    ) {}
}
