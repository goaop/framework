<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Demo\Example;

/**
 * Example class to show how to dynamically add new interfaces and traits to the class
 */
class IntroductionDemo
{

    /**
     * Method that checks if the current instance implementing Serializable interface
     */
    public function testSerializable()
    {
        if ($this instanceof \Serializable) {
            echo get_class($this), ' implements `Serializable` interface now!', PHP_EOL;
            $reflection = new \ReflectionObject($this);
            var_dump($reflection->getInterfaceNames(), $reflection->getTraitNames());
        } else {
            echo get_class($this), ' does not implement `Serializable` interface', PHP_EOL;
        }
    }
}
