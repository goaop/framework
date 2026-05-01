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
 * returning proper type for instance `SomeConcreteType`. Same applies to the {@see self::getScope()} method -
 * it will return proper type for instance `SomeConcreteType`.
 *
 * Second generic variable `<V>` declares the generic return type of the method invocation. Specify it to have better
 * code type completion and validation of return types. Take original method return type and put it here.
 *
 * If your pointcut targets only dynamic method calls, you can use {@see DynamicMethodInvocation} interface instead
 * to give IDE and static analysis tools information about non-static context of the method call.
 *
 * @api
 *
 * @template T of object = object Declares the instance type of the method invocation.
 * @template V = mixed Declares the generic return type of the method invocation.
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
}
