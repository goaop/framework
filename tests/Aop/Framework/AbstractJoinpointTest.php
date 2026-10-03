<?php

declare(strict_types=1);

namespace Go\Aop\Framework;

use Go\Aop\Advice;
use Go\Aop\AdviceTypeEnum;
use Go\Aop\AspectException;
use Go\Aop\OrderedAdvice;
use LogicException;
use PhpParser\Node\Expr;
use PHPUnit\Framework\TestCase;

class AbstractJoinpointTest extends TestCase
{
    protected AbstractJoinpoint $joinpoint;

    /**
     * @param list<Advice> $advices
     * @param list<AdviceTypeEnum|class-string> $order
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('sortingTestSource')]
    public function testSortingLogic(array $advices, array $order = []): void
    {
        $advices = AbstractJoinpoint::sortAdvices($advices);
        foreach ($advices as $advice) {
            $expected = array_shift($order);
            if ($expected instanceof AdviceTypeEnum) {
                $this->assertInstanceOf(Advice::class, $advice);
                $this->assertSame($expected, $advice->getType());
            } else {
                assert($expected !== null);
                $this->assertInstanceOf($expected, $advice);
            }
        }
    }

    /**
     * @return array<array{list<Advice>, list<AdviceTypeEnum|class-string>}>
     */
    public static function sortingTestSource(): array
    {
        $after  = self::makeAdvice(AdviceTypeEnum::After);
        $before = self::makeAdvice(AdviceTypeEnum::Before);
        $around = self::makeAdvice(AdviceTypeEnum::Around);

        $forth = self::makeOrderedAdvice(4);
        $first = self::makeOrderedAdvice(1);

        return [
            // #0
            [
                [clone $after, clone $before],
                [AdviceTypeEnum::Before, AdviceTypeEnum::After],
            ],
            // #1
            [
                [clone $after, clone $around],
                [AdviceTypeEnum::After, AdviceTypeEnum::Around],
            ],
            // #2
            [
                [clone $before, clone $after],
                [AdviceTypeEnum::Before, AdviceTypeEnum::After],
            ],
            // #3
            [
                [clone $before, clone $around],
                [AdviceTypeEnum::Before, AdviceTypeEnum::Around],
            ],
            // #4
            [
                [clone $around, clone $after],
                [AdviceTypeEnum::After, AdviceTypeEnum::Around],
            ],
            // #5
            [
                [clone $around, clone $before],
                [AdviceTypeEnum::Before, AdviceTypeEnum::Around],
            ],
            // #6
            [
                [clone $before, clone $around, clone $before, clone $after],
                [AdviceTypeEnum::Before, AdviceTypeEnum::Before, AdviceTypeEnum::After, AdviceTypeEnum::Around],
            ],
            // #7
            [
                [$forth, $first],
                [get_class($first), get_class($forth)],
            ],
        ];
    }

    public function testFlatAndSortAdvicesGeneratesDescriptorsForEveryAdviceType(): void
    {
        $noop     = static fn(): mixed => null;
        $advices  = [
            'method' => [
                'execute' => [
                    'advisor.around'        => new AroundInterceptor($noop),
                    'advisor.afterThrowing' => new AfterThrowingInterceptor($noop),
                    'advisor.after'         => new AfterInterceptor($noop),
                    'advisor.before'        => new BeforeInterceptor($noop),
                ],
            ],
        ];

        $flattened = AbstractJoinpoint::flatAndSortAdvices($advices);

        $descriptors = $flattened['method']['execute'];
        $this->assertContainsOnlyInstancesOf(GeneratedInterceptor::class, $descriptors);
        $this->assertSame(
            ['before', 'afterThrowing', 'after', 'around'],
            array_map(static fn(GeneratedInterceptor $descriptor): string => $descriptor->factoryMethod, $descriptors),
        );
        $this->assertSame(
            ['advisor.before', 'advisor.afterThrowing', 'advisor.after', 'advisor.around'],
            array_map(static fn(GeneratedInterceptor $descriptor): string => $descriptor->advisorId, $descriptors),
        );
    }

    public function testFlatAndSortAdvicesKeepsIntroductionAdvisorIds(): void
    {
        $advices = [
            'introduction' => [
                'root' => [
                    // @phpstan-ignore argument.type, argument.type (placeholder trait/interface names that are never loaded)
                    '\Some\Interface' => new TraitIntroductionInfo('\Some\Trait', '\Some\Interface'),
                ],
            ],
        ];

        $flattened = AbstractJoinpoint::flatAndSortAdvices($advices);

        $this->assertSame(['\Some\Interface'], $flattened['introduction']['root']);
    }

    public function testFlatAndSortAdvicesRejectsNonAdviceValues(): void
    {
        $this->expectException(AspectException::class);
        $this->expectExceptionMessage('instead of advice instance');

        AbstractJoinpoint::flatAndSortAdvices([
            'method' => [
                'execute' => [
                    'advisor.broken' => true,
                ],
            ],
        ]);
    }

    private static function makeAdvice(AdviceTypeEnum $type): Advice
    {
        return new class ($type) implements Advice {
            public function __construct(private readonly AdviceTypeEnum $type) {}

            public function getType(): AdviceTypeEnum
            {
                return $this->type;
            }

            public function compileToPhp(): Expr
            {
                throw new LogicException('Not expected to be called');
            }
        };
    }

    private static function makeOrderedAdvice(int $order): OrderedAdvice
    {
        return new class ($order) implements OrderedAdvice {
            public function __construct(private readonly int $order) {}

            public function getAdviceOrder(): int
            {
                return $this->order;
            }

            public function getType(): AdviceTypeEnum
            {
                return AdviceTypeEnum::Introduction;
            }

            public function compileToPhp(): Expr
            {
                throw new LogicException('Not expected to be called');
            }
        };
    }
}
