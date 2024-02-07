<?php

declare(strict_types = 1);

namespace Go\Aop\Framework;

use Go\Aop\AdviceAfter;
use Go\Aop\AdviceAround;
use Go\Aop\AdviceBefore;
use PHPUnit\Framework\TestCase;

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
                    static::createMock(AdviceAfter::class),
                    static::createMock(AdviceBefore::class)
                ],
                [
                    AdviceBefore::class,
                    AdviceAfter::class
                ]
            ],
            // #1
            [
                [
                    static::createMock(AdviceAfter::class),
                    static::createMock(AdviceAround::class)
                ],
                [
                    AdviceAfter::class,
                    AdviceAround::class
                ]
            ],
            // #2
            [
                [
                    static::createMock(AdviceBefore::class),
                    static::createMock(AdviceAfter::class)
                ],
                [
                    AdviceBefore::class,
                    AdviceAfter::class
                ]
            ],
            // #3
            [
                [
                    static::createMock(AdviceBefore::class),
                    static::createMock(AdviceAround::class)
                ],
                [
                    AdviceBefore::class,
                    AdviceAround::class
                ]
            ],
            // #4
            [
                [
                    static::createMock(AdviceAround::class),
                    static::createMock(AdviceAfter::class)
                ],
                [
                    AdviceAfter::class,
                    AdviceAround::class
                ]
            ],
            // #5
            [
                [
                    static::createMock(AdviceAround::class),
                    static::createMock(AdviceBefore::class)
                ],
                [
                    AdviceBefore::class,
                    AdviceAround::class
                ]
            ],
            // #6
            [
                [
                    static::createMock(AdviceBefore::class),
                    static::createMock(AdviceAround::class),
                    static::createMock(AdviceBefore::class),
                    static::createMock(AdviceAfter::class),
                ],
                [
                    AdviceBefore::class,
                    AdviceBefore::class,
                    AdviceAfter::class,
                    AdviceAround::class,
                ]
            ],
            // #7
            [
                [
                    $forth = static::getOrderedAdvice(4),
                    $first = static::getOrderedAdvice(1)
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
    private static function getOrderedAdvice(int $order): OrderedAdvice
    {
        $mock = static::createMock(OrderedAdvice::class);
        $mock
            ->method('getAdviceOrder')
            ->willReturn($order);

        return $mock;
    }
}
