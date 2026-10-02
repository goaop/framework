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

namespace Go\Aop\Pointcut;

use Dissect\Parser\LALR1\Analysis\Analyzer;
use PHPUnit\Framework\TestCase;

/**
 * The checked-in parse table must be the one the grammar produces
 */
final class PointcutParseTableTest extends TestCase
{
    public function testCheckedInTableMatchesTheGrammar(): void
    {
        $analysis = (new Analyzer())->analyze(new PointcutGrammar());
        $this->assertSame([], $analysis->getResolvedConflicts(), 'The pointcut grammar must stay conflict-free');

        $checkedInTable = include __DIR__ . '/../../../src/Aop/Pointcut/PointcutParseTable.php';
        $this->assertSame(
            $analysis->getParseTable(),
            $checkedInTable,
            'PointcutParseTable.php is outdated, run: composer regenerate:parse-table',
        );
    }
}
