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

use FilesystemIterator;
use Go\Functional\BaseFunctionalTestCase;
use Go\PhpUnit\UsesTemporaryDirectory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Primes an opcache file cache on real disk for the warmed-up fixture project
 */
class OpcacheWarmupCommandTest extends BaseFunctionalTestCase
{
    use UsesTemporaryDirectory;

    private string $fileCacheDir;

    public function setUp(): void
    {
        // The base tearDown() clears the cache directory of the configuration, also after a skip
        $this->loadConfiguration();
        if (!function_exists('opcache_get_status')) {
            $this->markTestSkipped('Priming the file cache needs the opcache extension');
        }
        parent::setUp();
        $this->fileCacheDir = self::createTemporaryDirectory('opcache-file-cache');
    }

    public function tearDown(): void
    {
        if (isset($this->fileCacheDir)) {
            self::removeTemporaryDirectory($this->fileCacheDir);
        }
        parent::tearDown();
    }

    public function testPrimesTheFileCacheWithTheWovenFilesAndTheApplication(): void
    {
        $process = $this->runOpcacheWarmup(true, ['--file-cache=' . $this->fileCacheDir, '--entry=' . $this->configuration['frontController']]);

        $this->assertTrue($process->isSuccessful(), $process->getOutput() . $process->getErrorOutput());
        $this->assertMatchesRegularExpression('/Priming the opcache file cache .* with \d+ files of the AOP cache and \d+ files of the application\./', $process->getOutput());
        $this->assertMatchesRegularExpression('/Compiled \d+ files\./', $process->getOutput());
        $this->assertStringNotContainsString('Files of the AOP cache or entry points that could not be compiled', $process->getOutput());

        $cachedFiles = $this->cachedFiles();
        // Keyed by the PHP build, then the absolute path of the compiled file
        $this->assertContains('var/cache/aspect/src/Application/MainOriginalTrait.php.bin', $cachedFiles, 'Woven file');
        $this->assertContains('var/cache/aspect/src/Application/Main.php.bin', $cachedFiles, 'Proxy');
        $this->assertContains('var/cache/aspect/_include.cache.bin', $cachedFiles, 'Metadata read by every request');
        $this->assertContains('web/index.php.bin', $cachedFiles, 'The loader and entry point');
        $this->assertContains('src/Application/Salutation.php.bin', $cachedFiles, 'Untransformed source below the include paths');
    }

    public function testFailsWithTheCommandLineToRunWithoutTheFileCacheSettings(): void
    {
        $process = $this->runOpcacheWarmup(false, ['--file-cache=' . $this->fileCacheDir]);

        $this->assertFalse($process->isSuccessful());
        $this->assertStringContainsString('Can not prime the opcache file cache: ', $process->getErrorOutput());
        $this->assertStringContainsString(
            '-d opcache.enable_cli=1 -d opcache.file_cache_only=1 -d opcache.file_cache=' . $this->fileCacheDir,
            $process->getErrorOutput(),
        );
        $this->assertStringContainsString('cache:warmup:opcache ' . $this->configuration['frontController'], $process->getErrorOutput());
        $this->assertSame([], $this->cachedFiles());
    }

    /**
     * @param list<string> $arguments
     */
    private function runOpcacheWarmup(bool $withFileCacheSettings, array $arguments): Process
    {
        $phpExecutable = (new PhpExecutableFinder())->find();
        assert($phpExecutable !== false);
        $commandLine = [$phpExecutable, '-d', 'opcache.jit=disable', '-d', 'opcache.jit_buffer_size=0'];
        if ($withFileCacheSettings) {
            $commandLine = [...$commandLine, '-d', 'opcache.enable_cli=1', '-d', 'opcache.file_cache_only=1', '-d', 'opcache.file_cache=' . $this->fileCacheDir];
        }
        $commandLine = [...$commandLine, $this->configuration['console'], '--no-ansi', 'cache:warmup:opcache', $this->configuration['frontController'], ...$arguments];

        $process = new Process($commandLine, null, ['GO_AOP_CONFIGURATION' => $this->getConfigurationName()]);
        $process->run();

        return $process;
    }

    /**
     * Returns the compiled scripts of the file cache by their path below the fixture project, with `/` separators
     *
     * OPcache stores a script as `<file cache>/<system id>/<absolute path>.bin`; on Windows the drive letter loses
     * its colon (`C:\app` becomes `C\app`), so the project directory is compared without it.
     *
     * @return list<string>
     */
    private function cachedFiles(): array
    {
        $projectDir = str_replace([':', '\\'], ['', '/'], (string) realpath(__DIR__ . '/../../Fixtures/project'));
        $files      = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->fileCacheDir, FilesystemIterator::SKIP_DOTS)) as $file) {
            assert($file instanceof SplFileInfo);
            $pathname = str_replace('\\', '/', $file->getPathname());
            $position = strpos($pathname, $projectDir . '/');
            if ($position !== false) {
                $files[] = substr($pathname, $position + strlen($projectDir) + 1);
            }
        }
        sort($files);

        return $files;
    }

    protected function getConfigurationName(): string
    {
        return 'production';
    }
}
