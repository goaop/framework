<?php

declare(strict_types = 1);
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
use Go\Instrument\FileSystem\Enumerator;

class DebugAdvisorCommandTest extends BaseFunctionalTestCase
{
    public function testItDisplaysAdvisorsDebugInfo()
    {
        $output = $this->execute('debug:advisor');

        $expected = [
            'List of registered advisors in the container',
            'Go\Tests\TestProject\Aspect\LoggingAspect->beforeMethod',
            '@execution(Go\Tests\TestProject\Annotation\Loggable)',
        ];

        foreach ($expected as $string) {
            $this->assertStringContainsString($string, $output);
        }
    }

    public function testItDisplaysStatedAdvisorDebugInfo()
    {
        $output        = self::execute('debug:advisor', ['--advisor=Go\Tests\TestProject\Aspect\LoggingAspect->beforeMethod']);
        $enumerator    = new Enumerator(realpath($this->configuration['appDir'].'/src'));
        $srcFilesCount = iterator_count($enumerator->enumerate());

        $expected = [
            sprintf('Total %s files to analyze.', $srcFilesCount),
            '-> matching method Go\Tests\TestProject\Application\Main->doSomething',
        ];

        foreach ($expected as $string) {
            $this->assertStringContainsString($string, $output);
        }
    }
}
