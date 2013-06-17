<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2013, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Demo\Example;

/**
 * Human class example
 */
class Human
{

    /**
     * Eat something
     */
    public function eat()
    {
        echo "Eating...", PHP_EOL;
    }

    /**
     * Clean the teeth
     */
    public function cleanTeeth()
    {
        echo "Cleaning teeth...", PHP_EOL;
    }

    /**
     * Washing up
     */
    public function washUp()
    {
        echo "Washing up...", PHP_EOL;
    }

    /**
     * Working
     */
    public function work()
    {
        echo "Working...", PHP_EOL;
    }

    /**
     * Go to sleep
     */
    public function sleep()
    {
        echo "Go to sleep...", PHP_EOL;
    }
}
