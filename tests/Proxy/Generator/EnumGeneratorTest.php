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

namespace Go\Proxy\Generator;

use PhpParser\Node\Expr\BinaryOp\Plus;
use PhpParser\Node\Expr\BinaryOp\ShiftLeft;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for EnumGenerator case emission.
 */
class EnumGeneratorTest extends TestCase
{
    /**
     * Scalar case values must be emitted as literals.
     */
    public function testEmitsScalarCaseValues(): void
    {
        $generator = new EnumGenerator('Demo', null, 'string');
        $generator->addEnumCase('Active', 'active');

        $this->assertStringContainsString("case Active = 'active';", $generator->generate());
    }

    /**
     * Pure (unit) enum cases have no value.
     */
    public function testEmitsPureCaseWithoutValue(): void
    {
        $generator = new EnumGenerator('Demo', null, null);
        $generator->addEnumCase('Standalone');

        $this->assertStringContainsString('case Standalone;', $generator->generate());
    }

    /**
     * Constant-expression case values passed as raw PhpParser Expr nodes (issue #600)
     * must be emitted verbatim instead of being silently dropped.
     */
    public function testEmitsExpressionCaseValuesVerbatim(): void
    {
        $generator = new EnumGenerator('Demo', null, 'int');
        $generator->addEnumCase('Negative', new UnaryMinus(new Int_(1)));
        $generator->addEnumCase('Shifted', new ShiftLeft(new Int_(1), new Int_(2)));
        $generator->addEnumCase(
            'FromConst',
            new Plus(new ClassConstFetch(new Name('self'), new Identifier('SHIFT')), new Int_(10))
        );

        $output = $generator->generate();

        $this->assertStringContainsString('case Negative = -1;', $output);
        $this->assertStringContainsString('case Shifted = 1 << 2;', $output);
        $this->assertStringContainsString('case FromConst = self::SHIFT + 10;', $output);
    }
}
