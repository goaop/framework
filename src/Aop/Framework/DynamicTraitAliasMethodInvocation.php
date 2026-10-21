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

use Go\Aop\Exception\InvocationException;
use Go\Aop\Intercept\DynamicMethodInvocation;
use ReflectionFunction;
use ReflectionMethod;

/**
 * Dynamic trait-alias method invocation calls instance methods via reflection.
 *
 * The callable is provided by the generated proxy code and points to the original method body:
 *  - For methods declared in the proxied class: `$this-><method>OriginalAlias(...)` — the private
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
     * ReflectionMethod pointing to the original method body, resolved on the first call that reaches it
     * (an around advice may never proceed):
     *  - For methods with a trait alias: the private `<method>OriginalAlias` alias.
     *  - For inherited methods without a trait alias: the prototype method from the parent class.
     */
    private ReflectionMethod $originalMethodToCall;

    /**
     * Hot path: runs on every intercepted call. The code is inlined on purpose and the frame handling is copied
     * into every joinpoint class: do not extract parts of it into methods, and do not add object allocations,
     * reflection or extra method calls here.
     */
    public function __invoke(object $instance, array $arguments = [], array $variadicArguments = []): mixed
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
     *
     * Hot path: runs on every intercepted call. The code is inlined on purpose and the frame handling is copied
     * into every joinpoint class: do not extract parts of it into methods, and do not add object allocations,
     * reflection or extra method calls here.
     */
    public function proceed(): mixed
    {
        if (isset($this->advices[$this->current])) {
            return $this->advices[$this->current++]->invoke($this);
        }

        return ($this->originalMethodToCall ??= $this->resolveOriginalMethod())->invokeArgs($this->instance, $this->arguments);
    }

    /**
     * @phpstan-return T Covariance, always instance of object
     */
    public function getThis(): object
    {
        return $this->instance;
    }

    /**
     * @return true Covariance, always true for dynamic method calls
     */
    public function isDynamic(): true
    {
        return true;
    }

    public function getScope(): string
    {
        return $this->instance::class;
    }

    /**
     * Resolves the method behind the original-body callable.
     *
     * Reflection is used as workaround for PHP bug https://bugs.php.net/bug.php?id=72326
     */
    private function resolveOriginalMethod(): ReflectionMethod
    {
        $reflectionClosure = new ReflectionFunction($this->closureToCall);
        $closureScopeClass = $reflectionClosure->getClosureScopeClass();
        if ($closureScopeClass === null) {
            throw InvocationException::closureScopeUnresolvable();
        }

        return new ReflectionMethod($closureScopeClass->getName(), $reflectionClosure->getName());
    }
}
