<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop;

use PHPUnit\Framework\TestCase;

class AdviceTypeEnumTest extends TestCase
{
    public function testAdvicesAreOrderedBeforeAfterAroundIntroduction(): void
    {
        $types = [
            AdviceTypeEnum::Introduction,
            AdviceTypeEnum::Around,
            AdviceTypeEnum::AfterThrowing,
            AdviceTypeEnum::Before,
            AdviceTypeEnum::After,
        ];
        usort($types, static fn(AdviceTypeEnum $left, AdviceTypeEnum $right): int => $left->compareTo($right));

        $this->assertSame(AdviceTypeEnum::Before, $types[0]);
        $this->assertSame([AdviceTypeEnum::Around, AdviceTypeEnum::Introduction], array_slice($types, 3));
    }

    public function testAfterAndAfterThrowingShareTheirPriority(): void
    {
        $this->assertSame(0, AdviceTypeEnum::After->compareTo(AdviceTypeEnum::AfterThrowing));
        $this->assertSame(0, AdviceTypeEnum::Around->compareTo(AdviceTypeEnum::Around));
        $this->assertSame(-1, AdviceTypeEnum::Before->compareTo(AdviceTypeEnum::After));
        $this->assertSame(1, AdviceTypeEnum::Introduction->compareTo(AdviceTypeEnum::Around));
    }
}
