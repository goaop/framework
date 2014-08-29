<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Go\Aop\Intercept\MethodInterceptor;

/**
 * Reflective method invocation implementation
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
