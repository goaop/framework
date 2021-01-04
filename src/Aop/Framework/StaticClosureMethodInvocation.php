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
 * Static closure method invocation is responsible to call static methods via closure
 */
final class StaticClosureMethodInvocation extends AbstractMethodInvocation
{
    /**
     * Closure to use
     */
    protected ?Closure $closureToCall = null;

    /**
     * Previous scope of invocation
     */
    protected ?string $previousScope = null;

    /**
     * For static calls we store given argument as 'scope' property
     */
    protected static string $propertyName = 'scope';

    /**
     * Proceeds all registered advices for the static method and returns an invocation result
     */
    public function proceed()
    {
        if (isset($this->advices[$this->current])) {
            $currentInterceptor = $this->advices[$this->current++];

            return $currentInterceptor->invoke($this);
        }

        // Rebind the closure if scope (class name) was changed since last time
        if ($this->previousScope !== $this->scope) {
            if ($this->closureToCall === null) {
                $this->closureToCall = self::getStaticInvoker(
                    $this->reflectionMethod->class,
                    $this->reflectionMethod->name
                );
            }
            $this->closureToCall = $this->closureToCall->bindTo(null, $this->scope);
            $this->previousScope = $this->scope;
        }

        return ($this->closureToCall)($this->arguments);
    }

    /**
     * Returns static method invoker for the concrete method in the class
     */
    protected static function getStaticInvoker(string $className, string $methodName): Closure
    {
        return fn(array $args) => forward_static_call_array([$className, $methodName], $args);
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
        return false;
    }

    /**
     * Returns the object for which current joinpoint is invoked
     *
     * @return object Instance of object or null for static call/unavailable context
     */
    final public function getThis(): ?object
    {
        return null;
    }

    /**
     * Returns the static scope name (class name) of this joinpoint.
     */
    final public function getScope(): string
    {
        // $this->scope contains the current class scope that was received via static::class
        return $this->scope;
    }
}
