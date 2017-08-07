<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop;

/**
 * Pointcut realization for PHP
 *
 * Pointcuts are defined as a predicate over the syntax-tree of the program, and define an interface that constrains
 * which elements of the base program are exposed by the pointcut. A pointcut picks out certain join points and values
 * at those points
 */
interface Pointcut extends PointFilter
{
    /**
     * Return the class filter for this pointcut.
     */
    public function getClassFilter() : PointFilter;
}
