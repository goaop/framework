<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Closure;

use function get_class;

/**
 * Dynamic closure method invocation is responsible to call dynamic methods via closure
 */
final class DynamicClosureMethodInvocation extends AbstractMethodInvocation
{
    /**
     * Closure to use
     */
    protected ?Closure $closureToCall = null;

    /**
     * Previous instance of invocation
     */
    protected ?object $previousInstance;

    /**
     * For dynamic calls we store given argument as 'instance' property
     */
    protected static string $propertyName = 'instance';

    /**
     * Invokes original method and return result from it
     */
    public function proceed()
    {
        if (isset($this->advices[$this->current])) {
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

        return ($this->closureToCall)(...$this->arguments);
    }

    /**
     * Returns the object for which current joinpoint is invoked
     *
     * @return object Covariance, always instance of object
     */
    final public function getThis(): object
    {
        return $this->instance;
    }

    /**
     * Checks if the current joinpoint is dynamic or static
     *
     * Dynamic joinpoint contains a reference to an object that can be received via getThis() method call
     *
     * @see ClassJoinpoint::getThis()
     */
    final public function isDynamic(): bool
    {
        return true;
    }

    /**
     * Returns the static scope name (class name) of this joinpoint.
     */
    final public function getScope(): string
    {
        // Due to optimization $this->scope won't be filled for each invocation
        // However, $this->instance always contains an object, so we can take it's name as a scope name
        return get_class($this->instance);
    }
}
