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
use Go\Aop\Intercept\Interceptor;
use Go\Aop\Intercept\StaticMethodInvocation;

/**
 * Static trait-alias method invocation calls static methods via a first-class callable
 * that is rebound to each caller's late-static-binding scope on invocation.
 *
 * The callable is provided by the generated proxy code and points to the original method body:
 *  - For methods declared in the proxied class: `self::__aop__<method>(...)` — the private
 *    alias created in the proxy's trait-use block.
 *  - For inherited methods (no trait alias): `parent::<method>(...)`.
 *
 * In both cases the callable is wrapped in a `static fn(array $args) => forward_static_call_array($callable, ...$args)`
 * shim (see constructor). This shim can be rebound via {@see Closure::bindTo()} on every call so that
 * `static::class` (late-static-binding) inside the original method body resolves to the correct subclass.
 *
 * @template T of object = object Declares the instance type of the method invocation.
 * @template V = mixed Declares the generic return type of the method invocation.
 * @extends AbstractMethodInvocation<T, V>
 * @implements StaticMethodInvocation<T, V>
 *
 * @phpstan-type StaticMethodInvocationFrame array{list<mixed>, class-string<T>, int}
 */
final class StaticTraitAliasMethodInvocation extends AbstractMethodInvocation implements StaticMethodInvocation
{
    /**
     * @var class-string<T> Class name scope for static invocation
     */
    private string $scope;

    /**
     * Stack frames to work with recursive calls or with cross-calls inside object
     *
     * @var array<int, StaticMethodInvocationFrame>
     */
    private array $stackFrames = [];

    /**
     * Constructor for static method invocation.
     *
     * @param array<Interceptor> $advices       List of advices for this invocation
     * @param class-string<T>    $className     Class, containing method to invoke
     * @param non-empty-string   $methodName    Name of the method to invoke
     * @param Closure            $closureToCall First-class callable to the original static method body,
     *                                          e.g. `self::__aop__method(...)` or `parent::method(...)`.
     */
    public function __construct(array $advices, string $className, string $methodName, Closure $closureToCall)
    {
        // Wrap in a static closure so that when we bindTo(null, $scope) in proceed(),
        // forward_static_call_array will use $scope as the late-static-binding class.
        // We cannot rebind $closureToCall directly because first-class callables from static
        // methods have a fixed scope.
        $shim = static fn(array $argumentsToCall): mixed => forward_static_call_array($closureToCall, $argumentsToCall);
        parent::__construct($advices, $className, $methodName, $shim);
    }

    /**
     * Invokes current method invocation with all interceptors
     *
     * @phpstan-param class-string<T> $scope              Static scope class name
     * @param list<mixed>             $arguments          List of arguments for method invocation
     * @param list<mixed>             $variadicArguments  Additional list of variadic arguments
     *
     * @return V Templated return type (mixed by default)
     */
    final public function __invoke(string $scope, array $arguments = [], array $variadicArguments = []): mixed
    {
        if ($this->level > 0) {
            $this->stackFrames[] = [$this->arguments, $this->scope, $this->current];
        }
        if ($variadicArguments !== []) {
            $arguments = [...$arguments, ...$variadicArguments];
        }
        try {
            ++$this->level;
            $this->current   = 0;
            $this->arguments = $arguments;
            $this->scope     = $scope;
            return $this->proceed();
        } finally {
            --$this->level;
            if ($this->level > 0 && ($stackFrame = array_pop($this->stackFrames))) {
                [$this->arguments, $this->scope, $this->current] = $stackFrame;
            } else {
                unset($this->scope);
                $this->arguments = [];
            }
        }
    }

    /**
     * @return V Covariant, always mixed
     */
    public function proceed(): mixed
    {
        if (isset($this->advices[$this->current])) {
            return $this->advices[$this->current++]->invoke($this);
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
