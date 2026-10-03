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

use Go\Console\Command\DebugWeavingCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
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

    public function testCommandsAreRegisteredAndInstantiatedLazily(): void
    {
        $instantiated = [];

        // The same loader as bin/aspect, recording which commands get instantiated
        $commandLoader = CommandLoader::create(
            static function (string $commandClass) use (&$instantiated): Command {
                $instantiated[] = $commandClass;

                return new $commandClass();
            },
        );
        $application = new Application('Go! AOP');
        $application->setCommandLoader($commandLoader);

        $this->assertSame(['cache:warmup:aop', 'debug:aspect', 'debug:advisor', 'debug:weaving'], $commandLoader->getNames());
        $command = $application->find('debug:weaving');

        $this->assertSame('debug:weaving', $command->getName());
        $this->assertSame([DebugWeavingCommand::class], $instantiated, 'Only the requested command must be instantiated');
    }

    public function testDefaultLoaderInstantiatesTheCommands(): void
    {
        $commandLoader = CommandLoader::create();

        $this->assertInstanceOf(DebugWeavingCommand::class, $commandLoader->get('debug:weaving'));
    }

    /**
     * @param list<string> $args
     */
    private function runConsoleCommand(string $command, array $args = []): Process
    {
        $phpExecutable = (new PhpExecutableFinder())->find();
        assert($phpExecutable !== false);
        $commandLine   = array_merge(
            [$phpExecutable, $this->console, $command],
            $args,
        );

        $process = new Process($commandLine);
        $process->run();

        return $process;
    }
}
