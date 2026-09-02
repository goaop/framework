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
use Go\Aop\Aspect;
use Go\Aop\AspectException;
use ReflectionClass;

/**
 * Factory facade for generated proxy interceptor declarations.
 *
 * Given an aspect class and an advice method name, every factory method returns a native
 * lazy proxy: the interceptor construction, the aspect resolution from the container and
 * the first-class advice callable creation are all deferred until the interceptor is
 * actually used (advice invocation or ordering). Interceptors whose pointcut never
 * matches are therefore never really constructed, and their aspects never instantiated.
 *
 * A ready advice closure (e.g. from {@see The::advice()}) constructs the interceptor eagerly.
 *
 * @internal Consumed by generated proxy code and compiled advisor caches, free to change between releases
 */
final class Interceptor
{
    /**
     * @param class-string<Aspect>|Closure $aspectClassOrAdvice
     */
    public static function before(Closure|string $aspectClassOrAdvice, ?string $methodName = null, int $order = 0, string $expression = ''): BeforeInterceptor
    {
        return self::createLazily(BeforeInterceptor::class, $aspectClassOrAdvice, $methodName, $order, $expression);
    }

    /**
     * @param class-string<Aspect>|Closure $aspectClassOrAdvice
     */
    public static function after(Closure|string $aspectClassOrAdvice, ?string $methodName = null, int $order = 0, string $expression = ''): AfterInterceptor
    {
        return self::createLazily(AfterInterceptor::class, $aspectClassOrAdvice, $methodName, $order, $expression);
    }

    /**
     * @param class-string<Aspect>|Closure $aspectClassOrAdvice
     */
    public static function around(Closure|string $aspectClassOrAdvice, ?string $methodName = null, int $order = 0, string $expression = ''): AroundInterceptor
    {
        return self::createLazily(AroundInterceptor::class, $aspectClassOrAdvice, $methodName, $order, $expression);
    }

    /**
     * @param class-string<Aspect>|Closure $aspectClassOrAdvice
     */
    public static function afterThrowing(Closure|string $aspectClassOrAdvice, ?string $methodName = null, int $order = 0, string $expression = ''): AfterThrowingInterceptor
    {
        return self::createLazily(AfterThrowingInterceptor::class, $aspectClassOrAdvice, $methodName, $order, $expression);
    }

    /**
     * Creates the interceptor as a native lazy proxy for aspect-method advices
     *
     * @template T of AbstractInterceptor
     *
     * @param class-string<T>              $interceptorClass
     * @param class-string<Aspect>|Closure $aspectClassOrAdvice
     *
     * @return T
     */
    private static function createLazily(
        string $interceptorClass,
        Closure|string $aspectClassOrAdvice,
        ?string $methodName,
        int $order,
        string $expression,
    ): AbstractInterceptor {
        if ($aspectClassOrAdvice instanceof Closure) {
            return new $interceptorClass($aspectClassOrAdvice, $order, $expression);
        }
        if ($methodName === null) {
            throw new AspectException('Advice method name is required when an aspect class name is given');
        }

        return new ReflectionClass($interceptorClass)->newLazyProxy(
            static fn(): AbstractInterceptor => new $interceptorClass(
                The::aspect($aspectClassOrAdvice)->$methodName(...),
                $order,
                $expression,
            ),
        );
    }
}
