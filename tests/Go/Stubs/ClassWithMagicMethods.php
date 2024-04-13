<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2024, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Stubs;

class ClassWithMagicMethods
{
    public string $__call = 'magic';

    /**
     * @param array<mixed> $arguments
     */
    public function __call(string $name, array $arguments): string
    {
       return $name;
    }

    /**
     * @param array<mixed> $arguments
     */
    public function __callMe(string $name, array $arguments): string
    {
        return $name;
    }

    /**
     * @param array<mixed> $arguments
     */
    public static function __callStatic(string $name, array $arguments): string
    {
        return $name;
    }

    public function notMagicMethod(string $name): string
    {
        return $name;
    }
}
