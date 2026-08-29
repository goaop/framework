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
use Go\Aop\Intercept\DynamicMethodInvocation;
use Go\Aop\Intercept\Interceptor;
use ReflectionFunction;
use ReflectionMethod;

/**
 * Dynamic trait-alias method invocation calls instance methods via reflection.
 *
 * The callable is provided by the generated proxy code and points to the original method body:
 *  - For methods declared in the proxied class: `$this->__aop__<method>(...)` — the private
 *    alias created in the proxy's trait-use block.
 *  - For inherited methods (no trait alias): `parent::<method>(...)`.
 *
 * Note: `ReflectionMethod::invokeArgs()` is used in {@see proceed()} because it is faster than
 * `Closure::call()` (see https://3v4l.org/DYj84) and reliably handles pass-by-reference
 * parameters (unlike `Closure::call()` which has known issues with by-ref args, see
 * https://bugs.php.net/bug.php?id=72326).
 *
 * @template T of object = object Declares the instance type of the method invocation.
 * @template V = mixed Declares the generic return type of the method invocation.
 * @extends AbstractMethodInvocation<T, V>
 * @implements DynamicMethodInvocation<T, V>
 *
 * @phpstan-type DynamicMethodInvocationFrame array{list<mixed>, T, int}
 */
final class DynamicTraitAliasMethodInvocation extends AbstractMethodInvocation implements DynamicMethodInvocation
{
    /**
     * Stack frames to work with recursive calls or with cross-calls inside object
     *
     * @var array<int, DynamicMethodInvocationFrame>
     */
    private array $stackFrames = [];

    /**
     * @phpstan-var T Instance of object for invoking
     */
    private object $instance;

    /**
     * ReflectionMethod pointing to the original method body:
     *  - For methods with a trait alias: the private `__aop__<method>` alias.
     *  - For inherited methods without a trait alias: the prototype method from the parent class.
     */
    private readonly ReflectionMethod $originalMethodToCall;

    /**
     * @param array<Interceptor> $advices       List of advices for this invocation
     * @param class-string<T>    $className     Class, containing method to invoke
     * @param non-empty-string   $methodName    Name of the method to invoke
     * @param Closure            $closureToCall First-class callable to the original method body,
     *                                          e.g. `$this->__aop__method(...)` for trait-aliased
     *                                          methods or `parent::method(...)` for inherited ones.
     */
    public function __construct(array $advices, string $className, string $methodName, Closure $closureToCall)
    {
        parent::__construct($advices, $className, $methodName, $closureToCall);

        // Logic with reflection is used as workaround for PHP bug https://bugs.php.net/bug.php?id=72326
        $reflectionClosure = new ReflectionFunction($closureToCall);
        $closureScopeClass = $reflectionClosure->getClosureScopeClass();
        if ($closureScopeClass === null) {
            throw new \RuntimeException('Cannot determine the scope class of the closure');
        }
        $this->originalMethodToCall = new ReflectionMethod(
            $closureScopeClass->getName(),
            $reflectionClosure->getName()
        );
    }

    final public function __invoke(object $instance, array $arguments = [], array $variadicArguments = []): mixed
    {
        if ($this->level > 0) {
            $this->stackFrames[] = [$this->arguments, $this->instance, $this->current];
        }
        if ($variadicArguments !== []) {
            $arguments = [...$arguments, ...$variadicArguments];
        }
        try {
            ++$this->level;
            $this->current   = 0;
            $this->arguments = $arguments;
            $this->instance  = $instance;
            return $this->proceed();
        } finally {
            --$this->level;
            if ($this->level > 0 && ($stackFrame = array_pop($this->stackFrames))) {
                [$this->arguments, $this->instance, $this->current] = $stackFrame;
            } else {
                unset($this->instance);
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

        return $this->originalMethodToCall->invokeArgs($this->instance, $this->arguments);
    }

    /**
     * @phpstan-return T Covariance, always instance of object
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
        return $this->instance::class;
    }
}
