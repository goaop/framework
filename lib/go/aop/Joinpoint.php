<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace go\aop;

/**
 * This interface represents a generic runtime joinpoint (in the AOP terminology).
 *
 * A runtime joinpoint is an event that occurs on a static joinpoint (i.e. a location in a the program). For instance,
 * an invocation is the runtime joinpoint on a method (static joinpoint).
 *
 * @package go
 * @subpackage aop
 */
interface Joinpoint
{
    /**
     * Proceed to the next interceptor in the Chain
     *
     * Typically this method is called inside previous closure, as instance of Joinpoint is passed to callback
     * Do not call this method directly, only inside callback closures.
     *
     * @param array $params Parameters for calling
     * @param Joinpoint $joinPoint Current instance of join point
     * @return mixed
     */
    public function proceed(array $params, Joinpoint $joinPoint);
}
