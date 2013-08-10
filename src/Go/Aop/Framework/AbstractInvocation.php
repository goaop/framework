<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Framework;

use Go\Aop\Intercept\Invocation;

/**
 * Abstract class for all invocations joinpoints
 *
 * It is an implementation of Go\Aop\Intercept\Invocation interface
 *
 * @see Go\Aop\Intercept\Invocation
 * @package go
 */
abstract class AbstractInvocation extends AbstractJoinpoint implements Invocation
{
    /**
     * Arguments for invocation
     *
     * @var array
     */
    protected $arguments = array();

    /**
     * Get the arguments as an array object.
     * It is possible to change element values within this array to change the arguments
     *
     * @return array the arguments of the invocation
     */
    public function getArguments()
    {
        return $this->arguments;
    }
}
