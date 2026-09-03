<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Go\Aop\AdviceTypeEnum;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\TestCase;

class TraitIntroductionInfoTest extends TestCase
{
    public function testGetTraitReturnsTraitPassedToConstructor(): void
    {
        $info = new TraitIntroductionInfo('\Some\Trait', '\Some\Interface');

        $this->assertSame('\Some\Trait', $info->getTrait());
    }

    public function testGetInterfaceReturnsInterfacePassedToConstructor(): void
    {
        $info = new TraitIntroductionInfo('\Some\Trait', '\Some\Interface');

        $this->assertSame('\Some\Interface', $info->getInterface());
    }

    public function testGetTypeIsAlwaysIntroduction(): void
    {
        $info = new TraitIntroductionInfo('\Some\Trait', '\Some\Interface');

        $this->assertSame(AdviceTypeEnum::Introduction, $info->getType());
    }

    public function testCompileToPhpEmitsNewExpressionWithClassConstFetches(): void
    {
        $info = new TraitIntroductionInfo(SampleIntroducedTrait::class, SampleIntroducedInterface::class);

        $expr = $info->compileToPhp();

        $printer = new Standard();
        $code    = $printer->prettyPrintExpr($expr);

        $this->assertSame(
            sprintf(
                'new \%s(\%s::class, \%s::class)',
                TraitIntroductionInfo::class,
                SampleIntroducedTrait::class,
                SampleIntroducedInterface::class,
            ),
            $code,
        );
    }
}

trait SampleIntroducedTrait
{
}

interface SampleIntroducedInterface
{
}
