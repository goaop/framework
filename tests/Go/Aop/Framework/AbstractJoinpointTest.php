<?php

declare(strict_types = 1);

namespace Go\Aop\Framework;

use Go\Aop\AdviceAfter;
use Go\Aop\AdviceAround;
use Go\Aop\AdviceBefore;
use Go\Aop\OrderedAdvice;
use PHPUnit\Framework\TestCase;

// Test implementations for static data provider
class TestAdviceAfter implements AdviceAfter
{
    public function invoke($invocation) { return null; }
}

class TestAdviceBefore implements AdviceBefore
{
    public function invoke($invocation) { return null; }
}

class TestAdviceAround implements AdviceAround
{
    public function invoke($invocation) { return null; }
}

class TestOrderedAdvice implements OrderedAdvice
{
    public function __construct(private int $order) {}
    public function getAdviceOrder(): int { return $this->order; }
    public function invoke($invocation) { return null; }
}

class AbstractJoinpointTest extends TestCase
{
    protected AbstractJoinpoint $joinpoint;

    /**
     * @param array $advices
     * @param array $order
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('sortingTestSource')]
    public function testSortingLogic(array $advices, array $order = []): void
    {
        $advices = AbstractJoinpoint::sortAdvices($advices);
        foreach ($advices as $advice) {
            $expected = array_shift($order);
            $this->assertInstanceOf($expected, $advice);
        }
    }

    public static function sortingTestSource(): array
    {
        return [
            // #0
            [
                [
                    new TestAdviceAfter(),
                    new TestAdviceBefore()
                ],
                [
                    TestAdviceBefore::class,
                    TestAdviceAfter::class
                ]
            ],
            // #1
            [
                [
                    new TestAdviceAfter(),
                    new TestAdviceAround()
                ],
                [
                    TestAdviceAfter::class,
                    TestAdviceAround::class
                ]
            ],
            // #2
            [
                [
                    new TestAdviceBefore(),
                    new TestAdviceAfter()
                ],
                [
                    TestAdviceBefore::class,
                    TestAdviceAfter::class
                ]
            ],
            // #3
            [
                [
                    new TestAdviceBefore(),
                    new TestAdviceAround()
                ],
                [
                    TestAdviceBefore::class,
                    TestAdviceAround::class
                ]
            ],
            // #4
            [
                [
                    new TestAdviceAround(),
                    new TestAdviceAfter()
                ],
                [
                    TestAdviceAfter::class,
                    TestAdviceAround::class
                ]
            ],
            // #5
            [
                [
                    new TestAdviceAround(),
                    new TestAdviceBefore()
                ],
                [
                    TestAdviceBefore::class,
                    TestAdviceAround::class
                ]
            ],
            // #6
            [
                [
                    new TestAdviceBefore(),
                    new TestAdviceAround(),
                    new TestAdviceBefore(),
                    new TestAdviceAfter(),
                ],
                [
                    TestAdviceBefore::class,
                    TestAdviceBefore::class,
                    TestAdviceAfter::class,
                    TestAdviceAround::class,
                ]
            ],
            // #7
            [
                [
                    $forth = new TestOrderedAdvice(4),
                    $first = new TestOrderedAdvice(1)
                ],
                [
                    get_class($first),
                    get_class($forth),
                ]
            ],
        ];
    }

    /**
     * Returns the ordered advice
     */
    private function getOrderedAdvice(int $order): OrderedAdvice
    {
        $mock = $this->createMock(OrderedAdvice::class);
        $mock
            ->method('getAdviceOrder')
            ->willReturn($order);

        return $mock;
    }
}
