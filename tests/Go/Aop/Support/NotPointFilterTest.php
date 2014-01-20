<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2014, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Support;

use Go\Aop\PointFilter;

class NotPointFilterTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider logicCases
     */
    public function testMatches(PointFilter $first, $expected)
    {
        $filter = new NotPointFilter($first);
        $result = $filter->matches(new \ReflectionClass(__CLASS__) /* anything */);
        $this->assertSame($expected, $result);
    }

    public function logicCases()
    {
        $true  = TruePointFilter::getInstance();
        $false = new NotPointFilter($true);
        return array(
            array($false, true),
            array($true, false)
        );
    }
}
