<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace go\aop\framework;

use org\aopalliance\intercept\Invocation;

/**
 * Abstract class for all invocations joinpoints
 *
 * It is an implementation of org\aopalliance\intercept\Invocation interface
 * 
 * @see org\aopalliance\intercept\Invocation
 * @package go
 */
abstract class AbstractInvocation extends AbstractJoinpoint implements Invocation
{
    /** @var array Arguments for invocation */
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
