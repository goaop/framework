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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Console\Command\Command;
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
        $this->expectExceptionMessage('does not exist or is not readable');

        $tester->execute(['loader' => '/path/to/missing/loader.php']);
    }

    public function testRequiresLoaderArgumentWhenNoKernelIsInitialized(): void
    {
        $instanceProperty = new ReflectionProperty(AspectKernel::class, 'instance');
        $previousInstance = $instanceProperty->getValue();
        $instanceProperty->setValue(null, null);

        try {
            (new CommandTester(new CacheWarmupCommand()))->execute([]);
            $this->fail('The loader argument must be required without an initialized kernel');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('pass the loader argument', $exception->getMessage());
        } finally {
            $instanceProperty->setValue(null, $previousInstance);
        }
    }

    public function testCacheDirOptionMustBeAPath(): void
    {
        try {
            (new CommandTester(new CacheWarmupCommand()))->execute([
                'loader'      => __DIR__ . '/../../../vendor/autoload.php',
                '--cache-dir' => ['not', 'a', 'path'],
            ]);
            $this->fail('An array is no cache directory');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('--cache-dir option must be a path', $exception->getMessage());
        }
    }

    #[RequiresPhpExtension('pcntl')]
    public function testSubscribesToTerminationSignals(): void
    {
        $this->assertSame([SIGINT, SIGTERM], (new CacheWarmupCommand())->getSubscribedSignals());
    }

    #[RequiresPhpExtension('pcntl')]
    public function testHandleSignalDefersShutdownToWarmupLoop(): void
    {
        $this->assertFalse((new CacheWarmupCommand())->handleSignal(SIGINT));
    }

    public function testSuccessfulWarmupReturnsSuccess(): void
    {
        $command = new class extends CacheWarmupCommand {
            protected function loadAspectKernel(InputInterface $input, OutputInterface $output): void
            {
                // No-op: kernel is not needed, warmup below is simulated
            }

            protected function createCacheWarmer(OutputInterface $output, bool $failFast = false, int $workers = 1, int $cachedFiles = 100, ?string $cacheDir = null): CacheWarmer
            {
                return new class extends CacheWarmer {
                    public function __construct()
                    {
                        // Deliberately skips the parent constructor: no kernel is involved
                    }

                    public function warmUp(): int
                    {
                        // Simulates a warmup that completes without being interrupted
                        return 0;
                    }
                };
            }
        };

        $tester = new CommandTester($command);

        $this->assertSame(Command::SUCCESS, $tester->execute(['loader' => 'unused.php']));
    }

    public function testCreateCacheWarmerBuildsWarmerForLoadedKernel(): void
    {
        $this->expectNotToPerformAssertions();

        $command = new class extends CacheWarmupCommand {
            public function exposeCreateCacheWarmer(AspectKernel $kernel): CacheWarmer
            {
                $this->aspectKernel = $kernel;

                return $this->createCacheWarmer(new NullOutput());
            }
        };

        $command->exposeCreateCacheWarmer($this->createStub(AspectKernel::class));
    }

    #[RequiresPhpExtension('pcntl')]
    public function testInterruptedWarmupMapsSignalToExitCode(): void
    {
        $command = new class extends CacheWarmupCommand {
            protected function loadAspectKernel(InputInterface $input, OutputInterface $output): void
            {
                // No-op: kernel is not needed, warmup below is simulated
            }

            protected function createCacheWarmer(OutputInterface $output, bool $failFast = false, int $workers = 1, int $cachedFiles = 100, ?string $cacheDir = null): CacheWarmer
            {
                return new class ($this) extends CacheWarmer {
                    public function __construct(private readonly CacheWarmupCommand $command)
                    {
                        // Deliberately skips the parent constructor: no kernel is involved
                    }

                    public function warmUp(): int
                    {
                        // Simulates SIGINT delivered while the warmup loop is running
                        $this->command->handleSignal(SIGINT);

                        return 0;
                    }
                };
            }
        };

        $tester   = new CommandTester($command);
        $exitCode = $tester->execute(['loader' => 'unused.php']);

        $this->assertSame(128 + SIGINT, $exitCode);
        $this->assertStringContainsString('interrupted by a signal', $tester->getDisplay());
    }

    public function testWarmupWithErrorsReturnsFailureAndPassesFailFast(): void
    {
        $command = new class extends CacheWarmupCommand {
            public ?bool $failFast = null;

            public ?int $workers = null;

            public ?int $cachedFiles = null;

            protected function loadAspectKernel(InputInterface $input, OutputInterface $output): void
            {
                // No-op: kernel is not needed, warmup below is simulated
            }

            protected function createCacheWarmer(OutputInterface $output, bool $failFast = false, int $workers = 1, int $cachedFiles = 100, ?string $cacheDir = null): CacheWarmer
            {
                $this->failFast    = $failFast;
                $this->workers     = $workers;
                $this->cachedFiles = $cachedFiles;

                return new class extends CacheWarmer {
                    public function __construct()
                    {
                        // Deliberately skips the parent constructor: no kernel is involved
                    }

                    public function warmUp(): int
                    {
                        // Simulates a warmup where one file failed to weave
                        return 1;
                    }
                };
            }
        };

        $tester = new CommandTester($command);

        $this->assertSame(Command::FAILURE, $tester->execute(['loader' => 'unused.php', '--fail-fast' => true]));
        $this->assertTrue($command->failFast);
        $this->assertSame(1, $command->workers, 'Weaves in the command process by default');
        $this->assertSame(100, $command->cachedFiles);

        $tester->execute(['loader' => 'unused.php', '--workers' => '6', '--cached-files' => '25']);
        $this->assertSame(6, $command->workers);
        $this->assertSame(25, $command->cachedFiles);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidPositiveIntegers(): iterable
    {
        yield 'zero workers'          => ['workers', '0'];
        yield 'negative workers'      => ['workers', '-2'];
        yield 'word'                  => ['workers', 'many'];
        yield 'fraction'              => ['workers', '1.5'];
        yield 'no cached files'       => ['cached-files', '0'];
        yield 'unlimited cached files' => ['cached-files', 'all'];
    }

    #[DataProvider('invalidPositiveIntegers')]
    public function testInvalidNumberIsRejectedBeforeTheKernelIsLoaded(string $option, string $value): void
    {
        $tester = new CommandTester(new CacheWarmupCommand());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches(sprintf('/^Option --%s must be a positive integer, got "%s"$/', $option, preg_quote($value, '/')));

        $tester->execute(['loader' => '/path/to/missing/loader.php', '--' . $option => $value]);
    }
}
