<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop;

/**
 * Base interface holding AOP advice (action to take at a joinpoint) and a filter determining the
 * applicability of the advice (such as a pointcut).
 */
interface Advisor
{
    /**
     * Return the advice part of this aspect. An advice may be an interceptor, a before advice, a throws advice, etc.
     *
     * @return Advice The advice that should apply if the pointcut matches
     */
    public function getAdvice();

    /**
     * Return whether this advice is associated with a particular instance or shared with all instances
     * of the advised class
     *
     * @return bool Whether this advice is associated with a particular target instance
     */
    public function isPerInstance();
}
