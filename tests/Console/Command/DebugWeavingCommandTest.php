<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2017, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Console\Command;

use Go\Functional\BaseFunctionalTestCase;

class DebugWeavingCommandTest extends BaseFunctionalTestCase
{
    public function testReportInconsistentWeaving(): void
    {
        $output = $this->execute('debug:weaving', ['-vv'], false, 1);
        // SymfonyStyle wraps blocks (at most 120 columns), also in the middle of a long path, and prefixes
        // the continuation lines of a note with "!": compare the messages without line breaks and spaces
        $compactOutput = (string) preg_replace(['/\R[ !]*/', '/\s+/'], '', $output);

        $this->assertStringContainsString('InconsistentlyWeavedClass.php"isgeneratedonsecond"warmup"pass.', $compactOutput);
        $this->assertStringContainsString('Main.php"isconsistentlyweaved.', $compactOutput);
        $this->assertStringContainsString('[ERROR]Weavingisunstable,thereare1reportederror(s).', $compactOutput);
    }

    protected function getConfigurationName(): string
    {
        return 'inconsistent_weaving';
    }
}
