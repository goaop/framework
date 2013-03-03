<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop;

/**
 * Pointcut realization for PHP
 *
 * Pointcuts are defined as a predicate over the syntax-tree of the program, and define an interface that constrains
 * which elements of the base program are exposed by the pointcut. A pointcut picks out certain join points and values
 * at those points
 *
 * @package go
 * @subpackage aop
 */
interface Pointcut
{
    /**
     * Return the class filter for this pointcut.
     *
     * @return PointFilter
     */
    public function getClassFilter();

    /**
     * Return the PointFilter for this pointcut.
     *
     * This can be method filter, property filter.
     *
     * @return PointFilter
     */
    public function getPointFilter();
}
