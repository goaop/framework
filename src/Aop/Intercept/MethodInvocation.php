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
 * Detailed information about the method can be obtained via {@see self::getMethod()} method which
 * returns {@see ReflectionMethod} instance of relevant method.
 *
 * Interceptor can read or modify invocation arguments via {@see Invocation} interface:
 *  - {@see Invocation::getArguments()} to read arguments
 *  - {@see Invocation::setArguments()} to modify arguments
 *
 * This interface is declared as generic. To get better code type completion, specify concrete generic type for
 * your parameter as `MethodInvocation<SomeConcreteType>` in your aspects to make {@see self::getThis()} method
 * returning proper type for instance `SomeConcreteType`. Same applied to the {@see self::getScope()} method -
 * it will return proper type for instance `SomeConcreteType`.
 *
 * If your pointcut targets only dynamic method calls, you can use {@see DynamicMethodInvocation} interface instead
 * to give IDE and static analysis tools information about non-static context of the method call.
 *
 * @api
 *
 * @template T of object = object
 * @extends ClassJoinpoint<T>
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
     * @phpstan-param T|class-string<T> $instanceOrScope Invocation instance (or class name for static methods)
     * @param list<mixed>         $arguments          List of arguments for method invocation
     * @param list<mixed>         $variadicArguments  Additional list of variadic arguments
     */
    public function __invoke(object|string $instanceOrScope, array $arguments = [], array $variadicArguments = []): mixed;
}
