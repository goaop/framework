<?php

namespace Go\Console\Command;

use PHPUnit_Framework_TestCase as TestCase;
use Symfony\Component\Process\Process;

class DebugAspectCommandTest extends TestCase
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

    /**
     * @test
     */
    public function testItDisplaysAspectsDebugInfo()
    {
        $process = $process = new Process(sprintf('php %s debug:aspect %s',
            realpath(__DIR__.'/../../../Fixtures/project/bin/console'),
            realpath(__DIR__.'/../../../Fixtures/project/web/index.php')
        ));
        
        $process->run();

        $this->assertTrue($process->isSuccessful(), 'Unable to execute "debug:aspect" command.');

        $output = $process->getOutput();

        $expected = [
            'Go\Tests\TestProject\ApplicationAspectKernel has following enabled aspects',
            'Go\Tests\TestProject\Aspect\LoggingAspect',
            'Go\Tests\TestProject\Aspect\LoggingAspect->beforeMethod'
        ];

        foreach ($expected as $string) {
            $this->assertContains($string, $output);
        }
    }
}
