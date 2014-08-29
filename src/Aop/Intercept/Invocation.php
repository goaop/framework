<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Intercept;

/**
 * This interface represents an invocation in the program
 *
 * An invocation is a joinpoint and can be intercepted by an
 * interceptor
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
