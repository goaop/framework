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

namespace Go\Aop\Intercept;

/**
 * Dynamic method invocation extends the `MethodInvocation` with type information about dynamic method calls.
 *
 * This type is mostly used for the PhpStan nullable type-safety, ensuring that the {@see self::getThis()} method
 * always returns an instance of object for all dynamic calls.
 *
 * Interface overrides the return type of {@see MethodInvocation::getThis()} method and narrows its return type to
 * the generic object for all dynamic calls, removing the nullability of the return type.
 *
 * Without this interface, aspect advice should use the null-safe operator `$invocation->getThis()?->method()` to avoid
 * type errors.
 *
 * @api
 *
 * @template T of object = object Declares the instance type of the method invocation.
 * @template V = mixed Declares the generic return type of the method invocation.
 * @extends MethodInvocation<T, V>
 *
 * @link https://wiki.php.net/rfc/nullsafe_operator
 */
interface DynamicMethodInvocation extends MethodInvocation
{
    /**
     * @phpstan-return T Covariance, always instance of object
     */
    public function getThis(): object;

    /**
     * @return true Covariance, always true for dynamic method calls
     */
    public function isDynamic(): true;

    /**
     * Invokes current method invocation with all interceptors
     *
     * @phpstan-param T           $instance          Invocation instance
     * @param         list<mixed> $arguments         List of arguments for method invocation
     * @param         list<mixed> $variadicArguments Additional list of variadic arguments
     *
     * @return V Templated return type (mixed by default)
     */
    public function __invoke(object $instance, array $arguments = [], array $variadicArguments = []): mixed;
}