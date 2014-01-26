<?php
/**
 * Go! AOP framework
 *
 * @copyright     Copyright 2014, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Support;

use Go\Aop\PointFilter;

class OrPointFilterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Tests that filter combined different kinds of filters
     */
    public function testKindIsCombined()
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

        $filter   = new OrPointFilter($first, $second);
        $expected = PointFilter::KIND_METHOD | PointFilter::KIND_FUNCTION | PointFilter::KIND_PROPERTY;
        $this->assertEquals($expected, $filter->getKind());
    }

    /**
     * @dataProvider logicCases
     */
    public function testMatches(PointFilter $first, PointFilter $second, $expected)
    {
        $filter = new OrPointFilter($first, $second);
        $result = $filter->matches(new \ReflectionClass(__CLASS__) /* anything */);
        $this->assertSame($expected, $result);
    }

    public function logicCases()
    {
        $true  = TruePointFilter::getInstance();
        $false = new NotPointFilter($true);
        return array(
            array($false, $false, false),
            array($false, $true, true),
            array($true, $false, true),
            array($true, $true, true)
        );
    }
}
