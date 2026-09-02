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

/**
 * Factory facade for generated proxy interceptor declarations.
 *
 * @internal Consumed by generated proxy code and compiled advisor caches, free to change between releases
 */
final class Interceptor
{
    public static function before(Closure $advice, int $order = 0, string $expression = ''): BeforeInterceptor
    {
        return new BeforeInterceptor($advice, $order, $expression);
    }

    public static function after(Closure $advice, int $order = 0, string $expression = ''): AfterInterceptor
    {
        return new AfterInterceptor($advice, $order, $expression);
    }

    public static function around(Closure $advice, int $order = 0, string $expression = ''): AroundInterceptor
    {
        return new AroundInterceptor($advice, $order, $expression);
    }

    public static function afterThrowing(Closure $advice, int $order = 0, string $expression = ''): AfterThrowingInterceptor
    {
        return new AfterThrowingInterceptor($advice, $order, $expression);
    }
}
