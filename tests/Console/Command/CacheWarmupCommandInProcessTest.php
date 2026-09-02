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

namespace Go\Console\Command;

use Go\Core\AspectKernel;
use Go\Instrument\ClassLoading\CacheWarmer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * In-process tests for the cache:warmup:aop command.
 *
 * The AspectKernel is a per-process singleton, so a real warmup through a loaded
 * kernel is covered by the functional CacheWarmupCommandTest which shells out.
 * These tests cover everything that is safe to run inside the PHPUnit process:
 * attribute-based metadata, input validation, signal handling and the
 * exit-code mapping with a simulated warmer.
 */
class CacheWarmupCommandInProcessTest extends TestCase
{
    public function testMetadataIsDefinedByAttribute(): void
    {
        $command = new CacheWarmupCommand();

        $this->assertSame('cache:warmup:aop', $command->getName());
        $this->assertSame('Warm up the cache with woven aspects', $command->getDescription());
        $this->assertStringContainsString('warm up the cache', $command->getHelp());
    }

    public function testFailsForInvalidLoaderPath(): void
    {
        $tester = new CommandTester(new CacheWarmupCommand());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid loader path');

        $tester->execute(['loader' => '/path/to/missing/loader.php']);
    }

    public function testRequiresLoaderArgument(): void
    {
        $tester = new CommandTester(new CacheWarmupCommand());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('loader');

        $tester->execute([]);
    }

    public function testSubscribesToTerminationSignals(): void
    {
        $this->assertSame([SIGINT, SIGTERM], (new CacheWarmupCommand())->getSubscribedSignals());
    }

    public function testHandleSignalDefersShutdownToWarmupLoop(): void
    {
        $this->assertFalse((new CacheWarmupCommand())->handleSignal(SIGINT));
    }

    public function testSuccessfulWarmupReturnsSuccess(): void
    {
        $command = new class () extends CacheWarmupCommand {
            protected function loadAspectKernel(InputInterface $input, OutputInterface $output): void
            {
                // No-op: kernel is not needed, warmup below is simulated
            }

            protected function createCacheWarmer(OutputInterface $output): CacheWarmer
            {
                return new class () extends CacheWarmer {
                    public function __construct()
                    {
                        // Deliberately skips the parent constructor: no kernel is involved
                    }

                    public function warmUp(): void
                    {
                        // Simulates a warmup that completes without being interrupted
                    }
                };
            }
        };

        $tester = new CommandTester($command);

        $this->assertSame(Command::SUCCESS, $tester->execute(['loader' => 'unused.php']));
    }

    public function testCreateCacheWarmerBuildsWarmerForLoadedKernel(): void
    {
        $command = new class () extends CacheWarmupCommand {
            public function exposeCreateCacheWarmer(AspectKernel $kernel): CacheWarmer
            {
                $this->aspectKernel = $kernel;

                return $this->createCacheWarmer(new NullOutput());
            }
        };

        $warmer = $command->exposeCreateCacheWarmer($this->createStub(AspectKernel::class));

        $this->assertInstanceOf(CacheWarmer::class, $warmer);
    }

    public function testInterruptedWarmupMapsSignalToExitCode(): void
    {
        $command = new class () extends CacheWarmupCommand {
            protected function loadAspectKernel(InputInterface $input, OutputInterface $output): void
            {
                // No-op: kernel is not needed, warmup below is simulated
            }

            protected function createCacheWarmer(OutputInterface $output): CacheWarmer
            {
                return new class ($this) extends CacheWarmer {
                    public function __construct(private readonly CacheWarmupCommand $command)
                    {
                        // Deliberately skips the parent constructor: no kernel is involved
                    }

                    public function warmUp(): void
                    {
                        // Simulates SIGINT delivered while the warmup loop is running
                        $this->command->handleSignal(SIGINT);
                    }
                };
            }
        };

        $tester   = new CommandTester($command);
        $exitCode = $tester->execute(['loader' => 'unused.php']);

        $this->assertSame(128 + SIGINT, $exitCode);
        $this->assertStringContainsString('interrupted by a signal', $tester->getDisplay());
    }
}
