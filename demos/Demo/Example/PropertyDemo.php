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
 * Example class to show how to intercept an access to the properties
 */
class PropertyDemo
{
    public $publicProperty = 123;

    protected $protectedProperty = 456;

    public function showProtected()
    {
        echo $this->protectedProperty, PHP_EOL;
    }

    public function setProtected($newValue)
    {
        $this->protectedProperty = $newValue;
    }
}
