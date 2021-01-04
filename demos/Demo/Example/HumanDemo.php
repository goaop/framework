<?php

declare(strict_types=1);
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
    public function eat(): void
    {
        echo "Eating...", PHP_EOL;
    }

    /**
     * Clean the teeth
     */
    public function cleanTeeth(): void
    {
        echo "Cleaning teeth...", PHP_EOL;
    }

    /**
     * Washing up
     */
    public function washUp(): void
    {
        echo "Washing up...", PHP_EOL;
    }

    /**
     * Working
     */
    public function work(): void
    {
        echo "Working...", PHP_EOL;
    }

    /**
     * Go to sleep
     */
    public function sleep(): void
    {
        echo "Go to sleep...", PHP_EOL;
    }
}
