<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Support;

use Go\Aop\PointFilter;

class AndPointFilterTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Tests that filter intersect different kinds of filters
     */
    public function testKindIsIntersected()
    {
        $first = $this->getMock('Go\Aop\PointFilter');
        $first
            ->expects($this->any())
            ->method('getKind')
            ->will($this->returnValue(PointFilter::KIND_METHOD | PointFilter::KIND_PROPERTY));

        $second = $this->getMock('Go\Aop\PointFilter');
        $second
            ->expects($this->any())
            ->method('getKind')
            ->will($this->returnValue(PointFilter::KIND_METHOD | PointFilter::KIND_FUNCTION));

        $filter = new AndPointFilter($first, $second);
        $this->assertEquals(PointFilter::KIND_METHOD, $filter->getKind());
    }

    /**
     * @dataProvider logicCases
     */
    public function testMatches(PointFilter $first, PointFilter $second, $expected)
    {
        $filter = new AndPointFilter($first, $second);
        $result = $filter->matches(new \ReflectionClass(__CLASS__) /* anything */);
        $this->assertSame($expected, $result);
    }

    public function logicCases()
    {
        $true  = TruePointFilter::getInstance();
        $false = new NotPointFilter($true);
        return array(
            array($false, $false, false),
            array($false, $true, false),
            array($true, $false, false),
            array($true, $true, true)
        );
    }
}
