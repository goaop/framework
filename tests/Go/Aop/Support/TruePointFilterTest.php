<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Support;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * TruePointFilter test case
 */
class TruePointFilterTest extends TestCase
{
    protected TruePointFilter $filter;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        $this->filter = TruePointFilter::getInstance();
    }

    /**
     * Test that true matcher always matches the class
     */
    public function testMatches(): void
    {
        // Works correctly with ReflectionClass
        $class = new ReflectionClass(self::class);
        $this->assertTrue($this->filter->matches($class));
    }
}
