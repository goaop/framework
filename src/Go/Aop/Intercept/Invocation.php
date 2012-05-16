<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Intercept;

/**
 * This interface represents an invocation in the program
 *
 * <p>An invocation is a joinpoint and can be intercepted by an
 * interceptor
 *
 * @author Rod Johnson
 * @author Lissachenko Alexander
 */
interface Invocation extends Joinpoint
{

    /**
     * Get the arguments as an array object.
     * It is possible to change element values within this array to change the arguments
     *
     * @return array the arguments of the invocation
     */
    public function getArguments();
}