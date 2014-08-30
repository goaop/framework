<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Go\Aop\Intercept\MethodInterceptor;

/**
 * Class-invocation of dynamic method in a class via closure rebinding for version PHP>=5.6
 *
 * This class uses splat operator '...' for faster invocation
 */
class ClosureDynamicMethodInvocation56 extends ClosureDynamicMethodInvocation
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

        // Fill the closure only once if it's empty
        if (!$this->closureToCall) {
            $this->closureToCall = $this->reflectionMethod->getClosure($this->instance);
        }

        // Rebind the closure if instance was changed since last time
        if ($this->previousInstance !== $this->instance) {
            $this->closureToCall    = $this->closureToCall->bindTo($this->instance, $this->reflectionMethod->class);
            $this->previousInstance = $this->instance;
        }

        $closureToCall = $this->closureToCall;

        return $closureToCall(...$this->arguments);
    }
}
