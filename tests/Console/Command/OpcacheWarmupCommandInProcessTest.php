<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Console\Command;

use Closure;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\Container;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\Instrument\ClassLoading\ComposerAutoloader;
use Go\PhpUnit\UsesTemporaryDirectory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * In-process tests for the cache:warmup:opcache command: the PHPUnit process has no opcache file cache, so the
 * command stops at its configuration check here; priming itself is covered by the functional OpcacheWarmupCommandTest
 */
class OpcacheWarmupCommandInProcessTest extends TestCase
{
    use UsesTemporaryDirectory;

    public function testMetadataIsDefinedByAttribute(): void
    {
        $command = new OpcacheWarmupCommand();

        $this->assertSame('cache:warmup:opcache', $command->getName());
        $this->assertSame('Prime the opcache file cache with the AOP cache and the application', $command->getDescription());
        $this->assertStringContainsString('opcache.file_cache_only=1', $command->getHelp());
    }

    public function testFailsWithTheCommandLineToRunWhenTheProcessHasNoFileCache(): void
    {
        $tester   = new CommandTester(new OpcacheWarmupCommand());
        $exitCode = $tester->execute(['loader' => __DIR__ . '/../../../vendor/autoload.php', '--file-cache' => '/var/cache/opcache']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Can not prime the opcache file cache: ', $tester->getDisplay());
        $this->assertStringContainsString('-d opcache.enable_cli=1 -d opcache.file_cache_only=1 -d opcache.file_cache=/var/cache/opcache', $tester->getDisplay());
    }

    public function testFileCacheOptionMustBeAPath(): void
    {
        try {
            (new CommandTester(new OpcacheWarmupCommand()))->execute([
                'loader'       => __DIR__ . '/../../../vendor/autoload.php',
                '--file-cache' => ['not', 'a', 'path'],
            ]);
            $this->fail('An array is no file cache directory');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('--file-cache option must be a path', $exception->getMessage());
        }
    }

    public function testEntryPointsMustBePaths(): void
    {
        try {
            (new CommandTester(new OpcacheWarmupCommand()))->execute([
                'loader'  => __DIR__ . '/../../../vendor/autoload.php',
                '--entry' => [['not', 'a', 'path']],
            ]);
            $this->fail('An array is no entry point');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('--entry option must be a path', $exception->getMessage());
        }
    }

    /**
     * The compiler of the command is the primer of this process: a file this process included compiles (or counts as
     * compiled), a missing one is reported
     */
    public function testCompilesWithThePrimerOfTheProcess(): void
    {
        $command = new class extends OpcacheWarmupCommand {
            /**
             * @param list<string> $files
             *
             * @return array{int, array<string, string|null>}
             */
            public function exposeCompileFiles(array $files): array
            {
                $results  = [];
                $compiled = $this->compileFiles($files, static function (string $file, ?string $error) use (&$results): void {
                    $results[$file] = $error;
                });

                return [$compiled, $results];
            }
        };

        [$compiled, $results] = $command->exposeCompileFiles([__FILE__, __DIR__ . '/does-not-exist.php']);

        $this->assertSame(1, $compiled);
        $this->assertNull($results[__FILE__]);
        $this->assertIsString($results[__DIR__ . '/does-not-exist.php']);
    }

    public function testEntryPointsMustExist(): void
    {
        try {
            (new CommandTester(new OpcacheWarmupCommand()))->execute([
                'loader'  => __DIR__ . '/../../../vendor/autoload.php',
                '--entry' => [__FILE__, __DIR__ . '/missing/index.php'],
            ]);
            $this->fail('A missing entry point must be rejected');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Entry point ' . __DIR__ . '/missing/index.php does not exist', $exception->getMessage());
        }
    }
    /**
     * Creates the command over a kernel of a temporary application, with the file cache check and the compilation
     * replaced: the files handed to the compiler are recorded in order, the given ones fail with their reason
     *
     * @param array<string, string> $failing
     * @param list<string>          $compiledFiles
     */
    private function createCommand(string $appDir, string $cacheDir, array $failing, array &$compiledFiles): OpcacheWarmupCommand
    {
        $kernel  = $this->createStub(AspectKernel::class);
        $options = [
            'debug'          => false,
            'appDir'         => $appDir,
            'cacheDir'       => $cacheDir,
            'cacheFileMode'  => 0770,
            'features'       => 0,
            'includePaths'   => [$appDir . '/src'],
            'excludePaths'   => [],
            'containerClass' => Container::class,
        ];
        $kernel->method('getOptions')->willReturn($options);
        $kernel->method('hasFeature')->willReturn(false);
        $cachePathManager = new CachePathManager($kernel);
        // Service.php is known as untransformed, Woven.php as woven: only the former is a file of the application
        $cachePathManager->setCacheState($appDir . '/src/Service.php', ['filemtime' => 1, 'filesize' => 1, 'cachedAt' => 1, 'cacheUri' => null]);
        $cachePathManager->setCacheState($appDir . '/src/Woven.php', ['filemtime' => 1, 'filesize' => 1, 'cachedAt' => 1, 'cacheUri' => $cacheDir . '/src/WovenOriginalTrait.php']);
        $container = $this->createStub(AspectContainer::class);
        $container->method('getService')->willReturn($cachePathManager);
        $kernel->method('getContainer')->willReturn($container);

        // Native separators of the real paths (`\\` on Windows) are normalized, the failing files are given with `/`
        $record = static function (string $file) use (&$compiledFiles): void {
            $compiledFiles[] = self::normalize($file);
        };

        return new class ($kernel, $failing, $record) extends OpcacheWarmupCommand {
            /**
             * @param array<string, string>  $failing
             * @param Closure(string): void $record
             */
            public function __construct(
                private readonly AspectKernel $kernel,
                private readonly array $failing,
                private readonly Closure $record,
            ) {
                parent::__construct();
            }

            protected function loadAspectKernel(InputInterface $input, OutputInterface $output): void
            {
                $this->aspectKernel = $this->kernel;
                $this->loaderPath   = __FILE__;
            }

            protected function fileCacheProblem(?string $fileCacheDir): ?string
            {
                return null;
            }

            protected function compileFiles(array $files, Closure $onFile): int
            {
                $compiled = 0;
                foreach ($files as $file) {
                    ($this->record)($file);
                    $error = $this->failing[str_replace('\\', '/', $file)] ?? null;
                    $compiled += $error === null ? 1 : 0;
                    $onFile($file, $error);
                }

                return $compiled;
            }
        };
    }

    /**
     * Returns a path with `/` separators, so that expectations read the same on every platform
     */
    private static function normalize(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * Lays out a temporary application with a cache directory, returns [appDir, cacheDir] with `/` separators
     *
     * @return array{string, string}
     */
    private function createApplication(): array
    {
        $appDir   = self::normalize(self::createTemporaryDirectory('opcache-app'));
        $cacheDir = $appDir . '/var/cache/aop';
        mkdir($appDir . '/src');
        mkdir($appDir . '/web');
        mkdir($cacheDir . '/src', 0777, true);
        file_put_contents($appDir . '/src/Service.php', "<?php\n");
        file_put_contents($appDir . '/src/Woven.php', "<?php\n");
        file_put_contents($appDir . '/web/index.php', "<?php\n");
        file_put_contents($cacheDir . '/_include.cache', "<?php return [];\n");
        file_put_contents($cacheDir . '/src/WovenOriginalTrait.php', "<?php\n");
        file_put_contents($cacheDir . '/src/Woven.php', "<?php\n");

        return [$appDir, $cacheDir];
    }

    public function testCompilesTheCacheTheEntryPointsAndTheApplication(): void
    {
        [$appDir, $cacheDir] = $this->createApplication();
        try {
            $compiledFiles = [];
            $tester        = new CommandTester($this->createCommand($appDir, $cacheDir, [], $compiledFiles));

            $exitCode = $tester->execute(['--entry' => [$appDir . '/web/index.php']], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

            $display = self::normalize($tester->getDisplay());
            $this->assertSame(Command::SUCCESS, $exitCode, $display);
            $this->assertMatchesRegularExpression('/^Priming the opcache file cache( <info>.*<\/info>)? with 5 files of the AOP cache and \d+ files of the application\./', $display);
            $this->assertStringNotContainsString('cache  with', $display, 'No empty directory name in the message');
            $this->assertMatchesRegularExpression('/Compiled \d+ files\./', $display);
            $this->assertStringNotContainsString('could not be compiled', $display);

            // The cache files, the loader and the entry point come first, every one of them
            $this->assertSame(
                [$cacheDir . '/_include.cache', $cacheDir . '/src/Woven.php', $cacheDir . '/src/WovenOriginalTrait.php', self::normalize(__FILE__), $appDir . '/web/index.php'],
                array_slice($compiledFiles, 0, 5),
            );
            $this->assertContains($appDir . '/src/Service.php', $compiledFiles, 'The untransformed source');
            $this->assertNotContains($appDir . '/src/Woven.php', $compiledFiles, 'A woven source is never loaded in production');
            $autoloadedFiles = ComposerAutoloader::ofProcess()->autoloadedFiles();
            $this->assertContains(self::normalize($autoloadedFiles[0]), $compiledFiles, 'Composer\'s autoloaded files');
            $this->assertStringContainsString('  ' . $appDir . '/web/index.php', $display, '-v lists every file');
        } finally {
            self::removeTemporaryDirectory($appDir);
        }
    }

    public function testFailsWhenAFileOfTheCacheDoesNotCompile(): void
    {
        [$appDir, $cacheDir] = $this->createApplication();
        try {
            $compiledFiles = [];
            $tester        = new CommandTester($this->createCommand($appDir, $cacheDir, [$cacheDir . '/src/Woven.php' => 'ParseError: unexpected token'], $compiledFiles));

            $exitCode = $tester->execute(['--file-cache' => '/var/cache/opcache'], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
            $display  = self::normalize($tester->getDisplay());

            $this->assertSame(Command::FAILURE, $exitCode, $display);
            $this->assertStringContainsString('Priming the opcache file cache /var/cache/opcache with ', $display);
            $this->assertStringContainsString('Files of the AOP cache or entry points that could not be compiled:', $display);
            $this->assertStringContainsString('  ' . $cacheDir . '/src/Woven.php: ParseError: unexpected token', $display);
            $this->assertStringNotContainsString('(skipped)', $display);
        } finally {
            self::removeTemporaryDirectory($appDir);
        }
    }

    public function testSkipsFilesOfTheApplicationThatDoNotCompile(): void
    {
        [$appDir, $cacheDir] = $this->createApplication();
        try {
            $compiledFiles = [];
            $tester        = new CommandTester($this->createCommand($appDir, $cacheDir, [$appDir . '/src/Service.php' => 'Zend OPcache could not compile file'], $compiledFiles));

            $exitCode = $tester->execute([]);
            $display  = self::normalize($tester->getDisplay());

            $this->assertSame(Command::SUCCESS, $exitCode, $display);
            $this->assertStringContainsString('Files of the application that could not be compiled (skipped):', $display);
            $this->assertStringContainsString('  ' . $appDir . '/src/Service.php: Zend OPcache could not compile file', $display);
            $this->assertStringNotContainsString('  ' . $cacheDir, $display, 'Compiled files are listed with -v only');
        } finally {
            self::removeTemporaryDirectory($appDir);
        }
    }
}
