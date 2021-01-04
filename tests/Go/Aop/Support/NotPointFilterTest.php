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

namespace Go\Aop\Support;

use Go\Aop\PointFilter;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class NotPointFilterTest extends TestCase
{
    /**
     * @dataProvider logicCases
     */
    public function testMatches(PointFilter $first, bool $expected): void
    {
        $filter = new NotPointFilter($first);
        $result = $filter->matches(new ReflectionClass(self::class));
        $this->assertSame($expected, $result);
    }

    public function logicCases(): array
    {
        $true  = TruePointFilter::getInstance();
        $false = new NotPointFilter($true);
        return [
            [$false, true],
            [$true, false]
        ];
    }
}
