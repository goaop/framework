<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Demo\Example;

use ReflectionObject;
use Stringable;

/**
 * Example class to show how to dynamically add new interfaces and traits to the class
 */
class IntroductionDemo
{
    public string $name = 'AOP';

    /**
     * Method that checks if the current instance implements Stringable interface
     */
    public function testStringable(): void
    {
        if ($this instanceof Stringable) {
            echo get_class($this), ' implements `Stringable` interface now!', PHP_EOL;
            echo 'String representation: ', $this, PHP_EOL;
            $reflection = new ReflectionObject($this);
            echo "List of interfaces:", PHP_EOL;
            foreach ($reflection->getInterfaceNames() as $interfaceName) {
                echo '-> ', $interfaceName, PHP_EOL;
            }
            echo "List of traits:", PHP_EOL;
            foreach ($reflection->getTraitNames() as $traitName) {
                echo '-> ', $traitName, PHP_EOL;
            }
        } else {
            echo get_class($this), ' does not implement `Stringable` interface', PHP_EOL;
        }
    }
}
