<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

class DynamicClosureMethodInvocation extends AbstractMethodInvocation
{
    /**
     * Closure to use
     *
     * @var \Closure
     */
    protected $closureToCall = null;

    /**
     * Previous instance/scope of invocation
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

    /**
     * Invokes current method invocation with all interceptors
     *
     * @param null|object|string $instance Invocation instance (class name for static methods)
     * @param array $arguments List of arguments for method invocation
     *
     * @return mixed Result of invocation
     */
    final public function __invoke($instance = null, array $arguments = [])
    {
        if ($this->level) {
            $this->stackFrames[] = [$this->arguments, $this->instance, $this->current];
        }

        try {
            ++$this->level;

            $this->current   = 0;
            $this->instance  = $instance;
            $this->arguments = $arguments;

            $result = $this->proceed();
        } finally {
            --$this->level;
        }

        if ($this->level) {
            list($this->arguments, $this->instance, $this->current) = array_pop($this->stackFrames);
        }

        return $result;
    }
}
