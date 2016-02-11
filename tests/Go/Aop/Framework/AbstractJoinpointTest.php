<?php

namespace Go\Aop\Framework;

use Go\Aop\AdviceAfter;
use Go\Aop\AdviceAround;
use Go\Aop\AdviceBefore;

class AbstractJoinpointTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var AbstractJoinpoint
     */
    protected $joinpoint;

    /**
     * @dataProvider sortingTestSource
     */
    public function testSortingLogic($advices, array $order = [])
    {
        $advices = AbstractJoinpoint::sortAdvices($advices);
        foreach ($advices as $advice) {
            $expected = array_shift($order);
            $this->assertInstanceOf($expected, $advice);
        }
    }

    public function sortingTestSource()
    {
        return array(
            // #0
            array(
                array(
                    $this->getMock(AdviceAfter::class),
                    $this->getMock(AdviceBefore::class)
                ),
                array(
                    AdviceBefore::class,
                    AdviceAfter::class
                )
            ),
            // #1
            array(
                array(
                    $this->getMock(AdviceAfter::class),
                    $this->getMock(AdviceAround::class)
                ),
                array(
                    AdviceAfter::class,
                    AdviceAround::class
                )
            ),
            // #2
            array(
                array(
                    $this->getMock(AdviceBefore::class),
                    $this->getMock(AdviceAfter::class)
                ),
                array(
                    AdviceBefore::class,
                    AdviceAfter::class
                )
            ),
            // #3
            array(
                array(
                    $this->getMock(AdviceBefore::class),
                    $this->getMock(AdviceAround::class)
                ),
                array(
                    AdviceBefore::class,
                    AdviceAround::class
                )
            ),
            // #4
            array(
                array(
                    $this->getMock(AdviceAround::class),
                    $this->getMock(AdviceAfter::class)
                ),
                array(
                    AdviceAfter::class,
                    AdviceAround::class
                )
            ),
            // #5
            array(
                array(
                    $this->getMock(AdviceAround::class),
                    $this->getMock(AdviceBefore::class)
                ),
                array(
                    AdviceBefore::class,
                    AdviceAround::class
                )
            ),
            // #6
            array(
                array(
                    $this->getMock(AdviceBefore::class),
                    $this->getMock(AdviceAround::class),
                    $this->getMock(AdviceBefore::class),
                    $this->getMock(AdviceAfter::class),
                ),
                array(
                    AdviceBefore::class,
                    AdviceBefore::class,
                    AdviceAfter::class,
                    AdviceAround::class,
                )
            ),
            // #7
            array(
                array(
                    $forth = $this->getOrderedAdvice(4, 'ForthAdvice'),
                    $first = $this->getOrderedAdvice(1, 'FirstAdvice')
                ),
                array(
                    get_class($first),
                    get_class($forth),
                )
            ),
        );
    }

    /**
     * Returns the ordered advice
     *
     * @param int $order Order
     * @param string $name Mock class name
     * @return \PHPUnit_Framework_MockObject_MockObject|OrderedAdvice
     */
    private function getOrderedAdvice($order, $name)
    {
        $mock = $this->getMock(OrderedAdvice::class, [], [], $name);
        $mock
            ->expects($this->any())
            ->method('getAdviceOrder')
            ->will(
                $this->returnValue($order)
            );

        return $mock;
    }
}
