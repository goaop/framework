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
use Go\Aop\Intercept\ClassJoinpoint;
use Go\Aop\Intercept\ConstructorInvocation;
use Go\Aop\Intercept\DynamicMethodInvocation;
use Go\Aop\Intercept\FieldAccess;
use Go\Aop\Intercept\FunctionInvocation;
use Go\Aop\Intercept\Interceptor;
use Go\Aop\Intercept\StaticMethodInvocation;

/**
 * Central factory for creating concrete joinpoint implementations.
 */
final class InterceptorInjector
{
    /**
     * @template T of object
     * @param class-string<T> $className
     * @param non-empty-string $methodName
     * @param non-empty-list<Interceptor> $interceptors
     * @param Closure $closureToCall First-class callable to the original method body,
     *                               e.g. `$this->methodOriginalAlias(...)` for trait-aliased methods or
     *                               `parent::method(...)` for inherited methods.
     * @return DynamicMethodInvocation<T>
     */
    public static function forMethod(string $className, string $methodName, array $interceptors, Closure $closureToCall): DynamicMethodInvocation
    {
        return new DynamicTraitAliasMethodInvocation(
            $interceptors,
            $className,
            $methodName,
            $closureToCall,
        );
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @param non-empty-string $methodName
     * @param non-empty-list<Interceptor> $interceptors
     * @param Closure $closureToCall First-class callable to the original static method body,
     *                               e.g. `self::methodOriginalAlias(...)` for trait-aliased methods or
     *                               `parent::method(...)` for inherited methods.
     * @return StaticMethodInvocation<T>
     */
    public static function forStaticMethod(string $className, string $methodName, array $interceptors, Closure $closureToCall): StaticMethodInvocation
    {
        return new StaticTraitAliasMethodInvocation(
            $interceptors,
            $className,
            $methodName,
            $closureToCall,
        );
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @param non-empty-string $propertyName
     * @param non-empty-list<Interceptor> $interceptors
     * @return FieldAccess<T>
     */
    public static function forProperty(string $className, string $propertyName, array $interceptors): FieldAccess
    {
        return new ClassFieldAccess(
            $interceptors,
            $className,
            $propertyName,
        );
    }

    /**
     * @param non-empty-string $functionName
     * @param non-empty-list<Interceptor> $interceptors
     * @param Closure $closureToCall First-class callable to the original global function
     *                               (e.g. `\file_get_contents(...)`).
     */
    public static function forFunction(string $functionName, array $interceptors, Closure $closureToCall): FunctionInvocation
    {
        return new ReflectionFunctionInvocation(
            $interceptors,
            $functionName,
            $closureToCall,
        );
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @param non-empty-list<Interceptor> $interceptors
     * @return ClassJoinpoint<T>
     */
    public static function forStaticInitialization(string $className, array $interceptors): ClassJoinpoint
    {
        return new StaticInitializationJoinpoint(
            $interceptors,
            $className,
        );
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @param non-empty-list<Interceptor> $interceptors
     * @return ConstructorInvocation<T>
     */
    public static function forInitialization(string $className, array $interceptors): ConstructorInvocation
    {
        return new ReflectionConstructorInvocation(
            $interceptors,
            $className,
        );
    }
}
