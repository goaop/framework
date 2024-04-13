<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2018, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Stubs;

use Go\Aop\Intercept\Invocation;

class FirstStatic extends First
{
    protected static ?Invocation $invocation;

    public static function init(Invocation $invocation)
    {
        static::$invocation = $invocation;
    }

    public static function staticLsbRecursion(int $value, int $level = 0): int
    {
        return static::$invocation->__invoke(self::class, [$value, $level]);
    }

    private static function privateStaticNever(): never
    {
        throw new \RuntimeException('Not implemented yet');
    }

    public static final function publicStaticFinal(): void
    {
        // nothing here
    }

    private function privateDynamicNever(): never
    {
        throw new \RuntimeException('Not implemented yet');
    }
}
