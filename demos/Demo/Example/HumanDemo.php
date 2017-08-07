<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Demo\Example;

/**
 * Human class example
 */
class HumanDemo
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
