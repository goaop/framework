<?php

declare(strict_types = 1);

namespace Go\Aop\Framework;

use Go\Aop\AdviceAfter;
use Go\Aop\AdviceAround;
use Go\Aop\AdviceBefore;
use Go\Aop\OrderedAdvice;
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
        $after  = new class implements AdviceAfter {};
        $before = new class implements AdviceBefore {};
        $around = new class implements AdviceAround {};

        $forth = self::makeOrderedAdvice(4);
        $first = self::makeOrderedAdvice(1);

        return [
            // #0
            [
                [clone $after, clone $before],
                [AdviceBefore::class, AdviceAfter::class]
            ],
            // #1
            [
                [clone $after, clone $around],
                [AdviceAfter::class, AdviceAround::class]
            ],
            // #2
            [
                [clone $before, clone $after],
                [AdviceBefore::class, AdviceAfter::class]
            ],
            // #3
            [
                [clone $before, clone $around],
                [AdviceBefore::class, AdviceAround::class]
            ],
            // #4
            [
                [clone $around, clone $after],
                [AdviceAfter::class, AdviceAround::class]
            ],
            // #5
            [
                [clone $around, clone $before],
                [AdviceBefore::class, AdviceAround::class]
            ],
            // #6
            [
                [clone $before, clone $around, clone $before, clone $after],
                [AdviceBefore::class, AdviceBefore::class, AdviceAfter::class, AdviceAround::class]
            ],
            // #7
            [
                [$forth, $first],
                [get_class($first), get_class($forth)]
            ],
        ];
    }

    private static function makeOrderedAdvice(int $order): OrderedAdvice
    {
        return new class($order) implements OrderedAdvice {
            public function __construct(private readonly int $order) {}

            public function getAdviceOrder(): int
            {
                return $this->order;
            }
        };
    }
}
