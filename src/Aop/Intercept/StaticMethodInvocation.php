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
 * Static method invocation extends MethodInvocation with type information about static method calls.
 *
 * @api
 *
 * @template T of object = object Declares the instance type of the method invocation.
 * @template V = mixed Declares the generic return type of the method invocation.
 * @extends MethodInvocation<T, V>
 */
interface StaticMethodInvocation extends MethodInvocation
{
    /**
     * @phpstan-return null Covariance, always null for static method calls
     */
    public function getThis(): null;

    /**
     * @return false Covariance, always false for static method calls
     */
    public function isDynamic(): false;
}