<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Framework;

/**
 * @package go
 */
class ReflectionMethodInvocation extends AbstractMethodInvocation
{
    /**
     * Invokes original method and return result from it
     *
     * @return mixed
     */
    protected function invokeOriginalMethod()
    {
        // Due to bug https://bugs.php.net/bug.php?id=60968 instance shouldn't be a string
        $instance = is_string($this->instance) ? null : $this->instance;
        return $this->getMethod()->invokeArgs($instance, $this->arguments);
    }
}
