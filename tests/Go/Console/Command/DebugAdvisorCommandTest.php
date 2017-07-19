<?php

namespace Go\Console\Command;

use PHPUnit_Framework_TestCase as TestCase;
use Symfony\Component\Process\Process;

class DebugAdvisorCommandTest extends TestCase
{
    public function setUp()
    {
        $process = new Process(sprintf('php %s cache:warmup:aop %s',
            realpath(__DIR__.'/../../../Fixtures/project/bin/console'),
            realpath(__DIR__.'/../../../Fixtures/project/web/index.php')
        ));

        $process->run();

        $this->assertTrue($process->isSuccessful(), 'Unable to execute "cache:warmup:aop" command.');
    }

    public function testItDisplaysAdvisorsDebugInfo()
    {
        $process = $process = new Process(sprintf('php %s debug:advisor %s',
            realpath(__DIR__.'/../../../Fixtures/project/bin/console'),
            realpath(__DIR__.'/../../../Fixtures/project/web/index.php')
        ));

        $process->run();

        $this->assertTrue($process->isSuccessful(), 'Unable to execute "debug:advisor" command.');

        $output = $process->getOutput();

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
        $process = $process = new Process(sprintf('php %s debug:advisor %s --advisor="Go\Tests\TestProject\Aspect\LoggingAspect->beforeMethod"',
            realpath(__DIR__.'/../../../Fixtures/project/bin/console'),
            realpath(__DIR__.'/../../../Fixtures/project/web/index.php')
        ));

        $process->run();

        $this->assertTrue($process->isSuccessful(), 'Unable to execute "debug:advisor" command.');

        $output = $process->getOutput();

        $expected = [
            'Total 4 files to analyze.',
            '-> matching method Go\Tests\TestProject\Application\Main->doSomething',
        ];

        foreach ($expected as $string) {
            $this->assertContains($string, $output);
        }
    }
}
