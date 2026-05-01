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
use Go\Core\AspectKernel;
use Go\Core\LazyAdvisorAccessor;

/**
 * Central factory for creating concrete joinpoint implementations.
 */
final class InterceptorInjector
{
    private static ?LazyAdvisorAccessor $accessor = null;

    /**
     * @template T of object
     * @param class-string<T> $className
     * @param non-empty-string $methodName
     * @param non-empty-list<string> $advisorNames
     * @param Closure $closureToCall First-class callable to the original method body,
     *                               e.g. `$this->__aop__method(...)` for trait-aliased methods or
     *                               `parent::method(...)` for inherited methods.
     * @return DynamicMethodInvocation<T>
     */
    public static function forMethod(string $className, string $methodName, array $advisorNames, Closure $closureToCall): DynamicMethodInvocation
    {
        return new DynamicTraitAliasMethodInvocation(
            self::fillInterceptors($advisorNames),
            $className,
            $methodName,
            $closureToCall
        );
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @param non-empty-string $methodName
     * @param non-empty-list<string> $advisorNames
     * @param Closure $closureToCall First-class callable to the original static method body,
     *                               e.g. `self::__aop__method(...)` for trait-aliased methods or
     *                               `parent::method(...)` for inherited methods.
     * @return StaticMethodInvocation<T>
     */
    public static function forStaticMethod(string $className, string $methodName, array $advisorNames, Closure $closureToCall): StaticMethodInvocation
    {
        return new StaticTraitAliasMethodInvocation(
            self::fillInterceptors($advisorNames),
            $className,
            $methodName,
            $closureToCall
        );
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @param non-empty-string $propertyName
     * @param non-empty-list<string> $advisorNames
     * @return FieldAccess<T>
     */
    public static function forProperty(string $className, string $propertyName, array $advisorNames): FieldAccess
    {
        return new ClassFieldAccess(
            self::fillInterceptors($advisorNames),
            $className,
            $propertyName
        );
    }

    /**
     * @param non-empty-string $functionName
     * @param non-empty-list<string> $advisorNames
     * @param Closure $closureToCall First-class callable to the original global function
     *                               (e.g. `\file_get_contents(...)`).
     */
    public static function forFunction(string $functionName, array $advisorNames, Closure $closureToCall): FunctionInvocation
    {
        return new ReflectionFunctionInvocation(
            self::fillInterceptors($advisorNames),
            $functionName,
            $closureToCall
        );
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @param non-empty-list<string> $advisorNames
     * @return ClassJoinpoint<T>
     */
    public static function forStaticInitialization(string $className, array $advisorNames): ClassJoinpoint
    {
        return new StaticInitializationJoinpoint(
            self::fillInterceptors($advisorNames),
            $className
        );
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @param non-empty-list<string> $advisorNames
     * @return ConstructorInvocation<T>
     */
    public static function forInitialization(string $className, array $advisorNames): ConstructorInvocation
    {
        return new ReflectionConstructorInvocation(
            self::fillInterceptors($advisorNames),
            $className
        );
    }

    /**
     * @param non-empty-list<string> $advisorNames
     * @return non-empty-list<Interceptor>
     */
    private static function fillInterceptors(array $advisorNames): array
    {
        if (self::$accessor === null) {
            self::$accessor = AspectKernel::getInstance()->getContainer()->getService(LazyAdvisorAccessor::class);
        }

        $filledAdvices = [];
        foreach ($advisorNames as $advisorName) {
            $filledAdvices[] = self::$accessor->getInterceptor($advisorName);
        }

        return $filledAdvices;
    }
}
