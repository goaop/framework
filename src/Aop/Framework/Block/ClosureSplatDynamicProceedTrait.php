<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework\Block;

use Go\Aop\Intercept\Interceptor;

trait ClosureSplatDynamicProceedTrait
{
    /**
     * Closure to use
     *
     * @var \Closure
     */
    protected $closureToCall = null;

    /**
     * Previous scope of invocation
     *
     * @var null
     */
    protected $previousInstance = null;

    /**
     * Invokes original method and return result from it
     *
     * @return mixed
     */
    public function proceed()
    {
        if (isset($this->advices[$this->current])) {
            /** @var $currentInterceptor Interceptor */
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
