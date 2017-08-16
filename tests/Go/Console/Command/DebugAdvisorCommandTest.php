<?php

namespace Go\Console\Command;

use Go\Functional\BaseFunctionalTest;
use Go\Instrument\FileSystem\Enumerator;

class DebugAdvisorCommandTest extends BaseFunctionalTest
{
    public function setUp()
    {
        self::clearCache();
        self::warmUp();
    }

    public function testItDisplaysAdvisorsDebugInfo()
    {
        $output = self::exec('debug:advisor');

        $expected = [
            'List of registered advisors in the container',
            'Go\Tests\TestProject\Aspect\LoggingAspect->beforeMethod',
            '@execution(Go\Tests\TestProject\Annotation\Loggable)',
        ];

        foreach ($expected as $string) {
            $this->assertContains($string, $output);
        }
    }

    public function testItDisplaysStatedAdvisorDebugInfo()
    {
        $output = self::exec('debug:advisor', '--advisor="Go\Tests\TestProject\Aspect\LoggingAspect->beforeMethod"');

        $enumerator = new Enumerator(realpath(self::$projectDir.'/src'));
        $srcFilesCount = iterator_count($enumerator->enumerate());

        $expected = [
            sprintf('Total %s files to analyze.', $srcFilesCount),
            '-> matching method Go\Tests\TestProject\Application\Main->doSomething',
        ];

        foreach ($expected as $string) {
            $this->assertContains($string, $output);
        }
    }
}
