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

use Go\Console\Command\CacheWarmupCommand;
use Go\Console\Command\DebugAdvisorCommand;
use Go\Console\Command\DebugAspectCommand;
use Go\Console\Command\DebugWeavingCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;
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
        $factory      = function (string $name, string $class) use (&$instantiated): callable {
            return static function () use (&$instantiated, $name, $class): object {
                $instantiated[] = $name;

                return new $class();
            };
        };

        // Mirrors the wiring of bin/aspect
        $application = new Application('Go! AOP');
        $application->setCommandLoader(new FactoryCommandLoader([
            'cache:warmup:aop' => $factory('cache:warmup:aop', CacheWarmupCommand::class),
            'debug:aspect'     => $factory('debug:aspect', DebugAspectCommand::class),
            'debug:advisor'    => $factory('debug:advisor', DebugAdvisorCommand::class),
            'debug:weaving'    => $factory('debug:weaving', DebugWeavingCommand::class),
        ]));

        $command = $application->find('debug:weaving');

        $this->assertSame('debug:weaving', $command->getName());
        $this->assertSame(['debug:weaving'], $instantiated, 'Only the requested command must be instantiated');
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
