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

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\Instrument\ClassLoading\CacheWarmer;
use Go\VirtualFileSystem\FileSystem;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * In-process tests for the debug:weaving command.
 *
 * The AspectKernel is a per-process singleton, so the real weaving consistency
 * checks stay covered by the functional DebugWeavingCommandTest which shells out.
 * These tests cover the attribute-based metadata, input validation and the
 * consistency/exit-code logic in-process against a faked kernel.
 */
class DebugWeavingCommandInProcessTest extends TestCase
{
    private ?FileSystem $fileSystem = null;

    public function testMetadataIsDefinedByAttribute(): void
    {
        $command = new DebugWeavingCommand();

        $this->assertSame('debug:weaving', $command->getName());
        $this->assertSame('Checks consistency in weaving process', $command->getDescription());
        $this->assertStringContainsString('compares the generated proxies', $command->getHelp());
    }

    public function testDiffShowsRemovedAndAddedLines(): void
    {
        $diff = new \ReflectionMethod(DebugWeavingCommand::class, 'diffLines')->invoke(null, "a\nb\nc", "a\nx\nc\nd");

        $this->assertSame(['<fg=red>- b</>', '<info>+ x</info>', '<info>+ d</info>'], $diff);
    }

    public function testFailsForInvalidLoaderPath(): void
    {
        $tester = new CommandTester(new DebugWeavingCommand());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid loader path');

        $tester->execute(['loader' => '/path/to/missing/loader.php']);
    }

    public function testRequiresLoaderArgument(): void
    {
        $tester = new CommandTester(new DebugWeavingCommand());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('loader');

        $tester->execute([]);
    }

    public function testBaseAspectCommandIsAbstract(): void
    {
        $this->assertTrue(new ReflectionClass(BaseAspectCommand::class)->isAbstract());
    }

    public function testStableWeavingReturnsSuccess(): void
    {
        $cacheDir = $this->createEmptyCacheDir();
        $tester   = new CommandTester($this->createCommandWithFakedKernel($cacheDir, static function (): void {}));

        $exitCode = $tester->execute(['loader' => 'unused.php']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Weaving is stable', $tester->getDisplay());
    }

    public function testInconsistentWeavingReturnsFailure(): void
    {
        $cacheDir = $this->createEmptyCacheDir();
        $calls    = 0;
        // On the second warmup pass a new proxy pair appears in the cache => inconsistency
        $warmUp = static function () use (&$calls, $cacheDir): void {
            if (++$calls === 2) {
                file_put_contents($cacheDir . '/Foo.php', '<?php // proxy');
                file_put_contents($cacheDir . '/Foo' . AspectContainer::ORIGINAL_TRAIT_FILE_SUFFIX, '<?php // trait');
            }
        };
        $tester = new CommandTester($this->createCommandWithFakedKernel($cacheDir, $warmUp));

        $exitCode = $tester->execute(['loader' => 'unused.php']);
        $this->cleanCacheDir($cacheDir);

        // SymfonyStyle wraps its blocks at the terminal width, so compare on one line
        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('generated on second "warmup" pass', $display);
        $this->assertStringContainsString('Weaving is unstable', $display);
    }

    public function testVerboseOutputShowsTheDifferenceAndTheStableProxies(): void
    {
        $cacheDir = $this->createEmptyCacheDir();
        $calls    = 0;
        // Foo is woven differently on the second pass, Bar the same on both
        $warmUp = static function () use (&$calls, $cacheDir): void {
            ++$calls;
            file_put_contents($cacheDir . '/Foo.php', "<?php\n// pass {$calls}\n");
            file_put_contents($cacheDir . '/Foo' . AspectContainer::ORIGINAL_TRAIT_FILE_SUFFIX, '<?php // trait');
            file_put_contents($cacheDir . '/Bar.php', '<?php // stable');
            file_put_contents($cacheDir . '/Bar' . AspectContainer::ORIGINAL_TRAIT_FILE_SUFFIX, '<?php // trait');
        };
        $tester = new CommandTester($this->createCommandWithFakedKernel($cacheDir, $warmUp));

        $exitCode = $tester->execute(['loader' => 'unused.php'], ['verbosity' => OutputInterface::VERBOSITY_VERY_VERBOSE]);
        foreach (['/Bar.php', '/Bar' . AspectContainer::ORIGINAL_TRAIT_FILE_SUFFIX] as $file) {
            unlink($cacheDir . $file);
        }
        $this->cleanCacheDir($cacheDir);

        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('is weaved differently on second "warmup" pass', $display);
        $this->assertStringContainsString('- // pass 1 + // pass 2', $display);
        $this->assertStringContainsString('Bar.php" is consistently', $display);
    }

    /**
     * Builds the command against a faked kernel: the AspectKernel is a per-process
     * singleton, so the kernel/container/warmer collaborators are replaced with
     * test doubles while the weaving consistency logic itself stays real.
     */
    private function createCommandWithFakedKernel(string $cacheDir, callable $warmUp): DebugWeavingCommand
    {
        $cachePathManager = $this->createStub(CachePathManager::class);
        $cachePathManager->method('getCacheDir')->willReturn($cacheDir);

        $container = $this->createStub(AspectContainer::class);
        $container->method('getService')->willReturnMap([
            [CachePathManager::class, $cachePathManager],
        ]);

        $kernel = $this->createStub(AspectKernel::class);
        $kernel->method('getContainer')->willReturn($container);

        $warmer = new class ($warmUp) extends CacheWarmer {
            /** @var callable */
            private $warmUpCallback;

            public function __construct(callable $warmUpCallback)
            {
                // Deliberately skips the parent constructor: no kernel is involved
                $this->warmUpCallback = $warmUpCallback;
            }

            public function warmUp(): int
            {
                ($this->warmUpCallback)();

                return 0;
            }
        };

        return new class ($kernel, $warmer) extends DebugWeavingCommand {
            public function __construct(
                private readonly AspectKernel $kernel,
                private readonly CacheWarmer $warmer,
            ) {
                parent::__construct();
            }

            protected function loadAspectKernel(InputInterface $input, OutputInterface $output): void
            {
                $this->aspectKernel = $this->kernel;
            }

            protected function createCacheWarmer(OutputInterface $output, bool $failFast = false): CacheWarmer
            {
                return $this->warmer;
            }
        };
    }

    private function createEmptyCacheDir(): string
    {
        $this->fileSystem = FileSystem::mount('debugweavingvfs');
        $cacheDir         = $this->fileSystem->path('/cache');
        mkdir($cacheDir, 0777, true);

        return $cacheDir;
    }

    private function cleanCacheDir(string $cacheDir): void
    {
        foreach (['/Foo.php', '/Foo' . AspectContainer::ORIGINAL_TRAIT_FILE_SUFFIX] as $knownFile) {
            if (is_file($cacheDir . $knownFile)) {
                unlink($cacheDir . $knownFile);
            }
        }
    }

    protected function tearDown(): void
    {
        $this->fileSystem?->unmount();
        $this->fileSystem = null;
    }
}
