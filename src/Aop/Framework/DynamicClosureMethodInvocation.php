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

/**
 * Dynamic closure method invocation is responsible to call dynamic methods via closure
 */
final class DynamicClosureMethodInvocation extends AbstractMethodInvocation
{
    /**
     * For dynamic calls we store given argument as 'instance' property
     *
     * @see parent::__invoke() method to find out how this optimization works
     * @see $instance Property, which is referenced by this static property
     */
    protected static string $propertyName = 'instance';

    /**
     * @var object Instance of object for invoking, should be protected as it's read in parent class
     * @see parent::__invoke() where this variable is accessed via {@see $propertyName} value
     */
    protected object $instance;

    /**
     * Closure to use
     */
    private ?Closure $closureToCall;

    /**
     * @var object Previous instance of invocation
     */
    private object $closureInstance;

    /**
     * @inheritdoc
     * @return mixed Covariant, always mixed
     */
    public function proceed(): mixed
    {
        if (isset($this->advices[$this->current])) {
            $currentInterceptor = $this->advices[$this->current++];

            return $currentInterceptor->invoke($this);
        }

        // Fill the closure only once if it's empty
        if (!isset($this->closureToCall)) {
            $this->closureToCall   = $this->reflectionMethod->getClosure($this->instance);
            $this->closureInstance = $this->instance;
        }

        // Rebind the closure if instance was changed since last time
        // This code won't work with {@see Closure::call()} as it fails to rebind closure created from method
        if ($this->closureInstance !== $this->instance) {
            $this->closureToCall   = $this->closureToCall?->bindTo($this->instance, $this->reflectionMethod->class);
            $this->closureInstance = $this->instance;
        }

        return ($this->closureToCall)?->__invoke(...$this->arguments);
    }

    /**
     * @inheritdoc
     *
     * @return object Covariance, always instance of object
     */
    final public function getThis(): object
    {
        return $this->instance;
    }

    /**
     * @return true Covariance, always true for dynamic method calls
     */
    final public function isDynamic(): true
    {
        return true;
    }

    final public function getScope(): string
    {
        // Due to optimization $this->scope won't be filled for each invocation
        // However, $this->instance always contains an object, so we can take it's name as a scope name
        return $this->instance::class;
    }
}
