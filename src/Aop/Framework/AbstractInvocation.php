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

namespace Go\Aop\Framework;

use Go\Aop\Intercept\Invocation;

/**
 * Abstract class for all invocations joinpoints
 */
abstract class AbstractInvocation extends AbstractJoinpoint implements Invocation
{
    /**
     * Arguments for invocation
     *
     * @var array
     */
    protected $arguments = [];

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

    /**
     * Sets the arguments for current invocation
     *
     * @param array $arguments New list of arguments
     */
    public function setArguments(array $arguments)
    {
        $this->arguments = $arguments;
    }
}
