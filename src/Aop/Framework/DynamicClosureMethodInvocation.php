<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

/**
 * Dynamic closure method invocation is responsible to call dynamic methods via closure
 */
final class DynamicClosureMethodInvocation extends AbstractMethodInvocation
{
    /**
     * Closure to use
     *
     * @var \Closure
     */
    protected $closureToCall = null;

    /**
     * Previous instance of invocation
     *
     * @var null|object|string
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
            /** @var $currentInterceptor \Go\Aop\Intercept\Interceptor */
            $currentInterceptor = $this->advices[$this->current++];

            return $currentInterceptor->invoke($this);
        }

        // Fill the closure only once if it's empty
        if ($this->closureToCall === null) {
            $this->closureToCall    = $this->reflectionMethod->getClosure($this->instance);
            $this->previousInstance = $this->instance;
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
