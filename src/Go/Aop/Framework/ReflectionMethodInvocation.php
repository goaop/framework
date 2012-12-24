<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Framework;

use Go\Aop\Intercept\MethodInterceptor;

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
    public function proceed()
    {
        if (isset($this->advices[$this->current])) {
            /** @var $currentInterceptor MethodInterceptor */
            $currentInterceptor = $this->advices[$this->current++];
            return $currentInterceptor->invoke($this);
        }

        // Due to bug https://bugs.php.net/bug.php?id=60968 instance shouldn't be a string
        $instance = ($this->instance !== (object) $this->instance) ? null : $this->instance;
        return $this->reflectionMethod->invokeArgs($instance, $this->arguments);
    }
}
