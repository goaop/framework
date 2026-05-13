<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2025, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class ApplicationTest extends TestCase
{
    private string $console;

    public function setUp(): void
    {
        $this->console = __DIR__ . '/../../bin/aspect';
    }

    public function testListCommandShowsAllRegisteredCommands(): void
    {
        $process = $this->runConsoleCommand('list', ['--no-ansi']);

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput() ?: $process->getOutput());
        $this->assertStringContainsString('cache:warmup:aop', $process->getOutput());
        $this->assertStringContainsString('debug:aspect', $process->getOutput());
        $this->assertStringContainsString('debug:advisor', $process->getOutput());
        $this->assertStringContainsString('debug:weaving', $process->getOutput());
    }

    public function testVersionOptionShowsApplicationVersion(): void
    {
        $process = $this->runConsoleCommand('list', ['--version']);

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput() ?: $process->getOutput());
        $this->assertStringContainsString('Go! AOP', $process->getOutput());
    }

    private function runConsoleCommand(string $command, array $args = []): Process
    {
        $phpExecutable = (new PhpExecutableFinder())->find();
        $commandLine   = array_merge(
            [$phpExecutable, $this->console, $command],
            $args
        );

        $process = new Process($commandLine);
        $process->run();

        return $process;
    }
}
