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
 * @template T of object = object
 * @extends MethodInvocation<T>
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
}