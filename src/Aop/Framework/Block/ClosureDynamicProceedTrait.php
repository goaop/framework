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

use Go\Aop\Intercept\MethodInterceptor;

trait ClosureDynamicProceedTrait
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
        $args          = $this->arguments;

        switch (count($args)) {
            case 0:
                return $closureToCall();
            case 1:
                return $closureToCall($args[0]);
            case 2:
                return $closureToCall($args[0], $args[1]);
            case 3:
                return $closureToCall($args[0], $args[1], $args[2]);
            case 4:
                return $closureToCall($args[0], $args[1], $args[2], $args[3]);
            case 5:
                return $closureToCall($args[0], $args[1], $args[2], $args[3], $args[4]);
            default:
                return forward_static_call_array($closureToCall, $args);
        }

    }
}