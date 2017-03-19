<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Demo\Example;

/**
 * Example class to show how to intercept magic methods
 *
 * @method void saveById(int $id)
 * @method void saveByName(string $name)
 * @method void load(int $id)
 * @method static void find(array $args)
 */
class DynamicMethodsDemo
{
    /**
     * Magic invoker
     *
     * @param string $name Method name
     * @param array $args Method arguments
     */
    public function __call($name, array $args)
    {
        echo "I'm method: {$name}", PHP_EOL;
    }

    /**
     * Magic static invoker
     *
     * @param string $name Method name
     * @param array $args Method arguments
     */
    public static function __callStatic($name, array $args)
    {
        echo "I'm static method: {$name}", PHP_EOL;
    }
}
