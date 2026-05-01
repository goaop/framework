<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Closure;
use Go\Aop\Intercept\StaticMethodInvocation;

/**
 * Static trait-alias method invocation calls static methods via a first-class callable
 * that is rebound to each caller's late-static-binding scope on every invocation.
 *
 * The callable is provided by the generated proxy code and points to the original method body:
 *  - For methods declared in the proxied class: `self::__aop__<method>(...)` — the private
 *    alias created in the proxy's trait-use block.
 *  - For inherited methods (no trait alias): `parent::<method>(...)`.
 *
 * In both cases the callable is wrapped in a `static fn(array $args) => forward_static_call($callable, ...$args)`
 * shim (see constructor).  This shim can be rebound via {@see Closure::bindTo()} on every call so that
 * `static::class` (late-static-binding) inside the original method body resolves to the correct subclass.
 *
 * @template T of object = object Declares the instance type of the method invocation.
 * @template V = mixed Declares the generic return type of the method invocation.
 * @extends AbstractMethodInvocation<T, V>
 * @implements StaticMethodInvocation<T, V>
 */
final class StaticTraitAliasMethodInvocation extends AbstractMethodInvocation implements StaticMethodInvocation
{
    /**
     * For static calls we store given argument as 'scope' property
     *
     * @see parent::__invoke() method to find out how this optimization works
     * @see $scope Property, which is referenced by this static property
     */
    protected static string $propertyName = 'scope';

    /**
     * @var class-string<T> Class name scope for static invocation
     */
    protected string $scope;

    /**
     * Constructor for static method invocation.
     *
     * Wraps the provided callable in a `static fn(array $args): mixed => forward_static_call($closureToCall, ...$args)`
     * shim so that `Closure::bindTo(null, $scope)` can forward the correct late-static-binding class to the
     * original method body without requiring the original closure to be rebindable.
     *
     * @param class-string<T> $className     Class, containing method to invoke
     * @param Closure         $closureToCall First-class callable to the original static method body,
     *                                       e.g. `self::__aop__method(...)` or `parent::method(...)`.
     */
    public function __construct(array $advices, string $className, string $methodName, Closure $closureToCall)
    {
        // Wrap in a static closure so that when we bindTo(null, $scope) in proceed(),
        // forward_static_call will use $scope as the late-static-binding class.
        // We cannot rebind $closureToCall directly because first-class callables from static
        // methods have a fixed scope.
        $shim = static fn(array $argumentsToCall): mixed => forward_static_call($closureToCall, ...$argumentsToCall);
        parent::__construct($advices, $className, $methodName, $shim);
    }

    /**
     * @return V Covariant, always mixed
     */
    public function proceed(): mixed
    {
        if (isset($this->advices[$this->current])) {
            $currentInterceptor = $this->advices[$this->current++];

            return $currentInterceptor->invoke($this);
        }

        // Bind the wrapper to the current scope so forward_static_call forwards the
        // correct late-static-binding class (supports child-class static invocations).
        return $this->closureToCall->bindTo(null, $this->scope)->__invoke($this->arguments);
    }

    /**
     * @return false Covariance, always false for static method calls
     */
    final public function isDynamic(): false
    {
        return false;
    }

    /**
     * @return null Covariance, always null for static invocations
     */
    final public function getThis(): null
    {
        return null;
    }

    final public function getScope(): string
    {
        return $this->scope;
    }
}
