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
 */
final class Interceptor
{
    public static function before(Closure $advice, int $order = 0): BeforeInterceptor
    {
        return new BeforeInterceptor($advice, $order);
    }

    public static function after(Closure $advice, int $order = 0): AfterInterceptor
    {
        return new AfterInterceptor($advice, $order);
    }

    public static function around(Closure $advice, int $order = 0): AroundInterceptor
    {
        return new AroundInterceptor($advice, $order);
    }

    public static function afterThrowing(Closure $advice, int $order = 0): AfterThrowingInterceptor
    {
        return new AfterThrowingInterceptor($advice, $order);
    }
}
