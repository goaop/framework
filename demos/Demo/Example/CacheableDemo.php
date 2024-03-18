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

use Demo\Attribute\Cacheable;

/**
 * Example class to show how to use caching with AOP
 */
class CacheableDemo
{
    /**
     * Returns a report and explicitly cache a result for future use
     *
     * In this example we use "Cacheable" annotation to explicit mark a method
     */
    #[Cacheable(time: 10)]
    public function getReport(string $from): string
    {
        // long calculation for 100ms
        usleep(100 * 1000);

        return $from;
    }
}
