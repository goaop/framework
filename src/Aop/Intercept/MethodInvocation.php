<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Intercept;

use ReflectionMethod;

/**
 * MethodInvocation interface represents an invocation of given method in the program.
 *
 * A method invocation is a joinpoint and can be intercepted by a method interceptor.
 *
 * Interceptor can read or modify invocation arguments via {@see Invocation} interface or
 * receive full information about invoked method via {@see self::getMethod()} method.
 *
 * @api
 */
interface MethodInvocation extends Invocation, ClassJoinpoint
{
    /**
     * Gets the method being called.
     *
     * @api
     */
    public function getMethod(): ReflectionMethod;

    /**
     * Invokes current method invocation with all interceptors
     *
     * @phpstan-param object|class-string $instanceOrScope    Invocation instance (or class name for static methods)
     * @phpstan-param list<mixed>         $arguments          List of arguments for method invocation
     * @phpstan-param list<mixed>         $variadicArguments  Additional list of variadic arguments
     */
    public function __invoke(object|string $instanceOrScope, array $arguments = [], array $variadicArguments = []): mixed;
}
