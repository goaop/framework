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
 */
final class DynamicTraitAliasMethodInvocation extends AbstractMethodInvocation implements DynamicMethodInvocation
{
    /**
     * For dynamic calls we store given argument as 'instance' property
     *
     * @see parent::__invoke() method to find out how this optimization works
     * @see $instance Property, which is referenced by this static property
     */
    protected static string $propertyName = 'instance';

    /**
     * @phpstan-var T Instance of object for invoking, should be protected as it's read in parent class
     * @see parent::__invoke() where this variable is accessed via {@see $propertyName} value
     */
    protected object $instance;

    /**
     * ReflectionMethod pointing to the original method body:
     *  - For methods with a trait alias: the private `__aop__<method>` alias.
     *  - For inherited methods without a trait alias: the prototype method from the parent class.
     */
    private readonly ReflectionMethod $originalMethodToCall;

    /**
     * @param array<Interceptor> $advices
     * @param class-string<T>    $className
     * @param non-empty-string   $methodName
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

    /**
     * @return V Covariant, always mixed
     */
    public function proceed(): mixed
    {
        if (isset($this->advices[$this->current])) {
            $currentInterceptor = $this->advices[$this->current++];

            return $currentInterceptor->invoke($this);
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
