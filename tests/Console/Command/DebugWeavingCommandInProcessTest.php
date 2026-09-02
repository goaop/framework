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
    public function testMetadataIsDefinedByAttribute(): void
    {
        $command = new DebugWeavingCommand();

        $this->assertSame('debug:weaving', $command->getName());
        $this->assertSame('Checks consistency in weaving process', $command->getDescription());
        $this->assertStringContainsString('consistency of weaving process', $command->getHelp());
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
                file_put_contents($cacheDir . '/Foo' . AspectContainer::AOP_PROXIED_SUFFIX . '.php', '<?php // trait');
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

            public function warmUp(): void
            {
                ($this->warmUpCallback)();
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

            protected function createCacheWarmer(OutputInterface $output): CacheWarmer
            {
                return $this->warmer;
            }
        };
    }

    private function createEmptyCacheDir(): string
    {
        $cacheDir = sys_get_temp_dir() . '/goaop-debug-weaving-cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }
        $this->cleanCacheDir($cacheDir);

        return (string) realpath($cacheDir);
    }

    private function cleanCacheDir(string $cacheDir): void
    {
        // Deletes only the exact files this test writes, never a glob/recursive sweep
        foreach (['/Foo.php', '/Foo' . AspectContainer::AOP_PROXIED_SUFFIX . '.php'] as $knownFile) {
            if (is_file($cacheDir . $knownFile)) {
                unlink($cacheDir . $knownFile);
            }
        }
    }
}
