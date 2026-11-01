<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2017, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Console\Command;

use Go\Functional\BaseFunctionalTestCase;
use Go\Tests\TestProject\Application\EdgeCaseDemo;
use Go\Tests\TestProject\Application\Main;
use Go\Tests\TestProject\Aspect\EdgeCaseAspect;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class CacheWarmupCommandTest extends BaseFunctionalTestCase
{
    // @phpstan-ignore phpunit.callParent (deliberately skips the parent cache warm-up - warming the cache is the test subject)
    public function setUp(): void
    {
        $this->loadConfiguration();
        $this->clearCache();
    }

    public function testItWarmsUpCache(): void
    {
        $this->assertFileDoesNotExist($this->configuration['cacheDir']);
        $this->warmUp();
        $this->assertClassIsWoven(Main::class);
    }

    /**
     * Workers weave the files, the command writes the cache metadata of all of them: the result is the cache of a
     * warmup in one process
     */
    public function testWorkersBuildTheSameCacheAsOneProcess(): void
    {
        $cacheDir = $this->configuration['cacheDir'];

        $output = $this->execute('cache:warmup:aop', ['--workers=1']);
        $this->assertStringNotContainsString('parallel worker processes', $output);
        $sequential = $this->readCache($cacheDir);
        $this->clearCache();

        $output = $this->execute('cache:warmup:aop', ['--workers=3']);
        $this->assertStringContainsString('Weaving in 3 parallel worker processes.', $output);
        $this->assertMatchesRegularExpression('/\[DONE\]: Total processed (\d+), 0 errors in .+ with 3 workers\./', $output);

        $this->assertEquals($sequential, $this->readCache($cacheDir));
        $this->assertClassIsWoven(Main::class);
    }

    /**
     * Returns every cached file by its path below the cache directory, the metadata files without the weaving times
     *
     * @return array<string, mixed>
     */
    private function readCache(string $cacheDir): array
    {
        $cacheDir = (string) realpath($cacheDir);
        $files    = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($cacheDir, \FilesystemIterator::SKIP_DOTS));
        $cache    = [];
        foreach ($files as $file) {
            assert($file instanceof \SplFileInfo);
            $path = substr($file->getPathname(), strlen($cacheDir));
            if (str_starts_with(basename($path), '_')) {
                $cache[$path] = self::withoutWeavingTimes(include $file->getPathname());
                continue;
            }
            $cache[$path] = file_get_contents($file->getPathname());
        }
        ksort($cache);

        return $cache;
    }

    /**
     * Drops the weaving times and sorts the maps: workers record the files in the order they finish them
     */
    private static function withoutWeavingTimes(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }
        unset($data['cachedAt']);
        $data = array_map(self::withoutWeavingTimes(...), $data);
        ksort($data);

        return $data;
    }

    /**
     * The cache of a deployment is built next to the running one: the other directory is complete and serves
     * the application as a prebuilt cache, while the directory of the kernel holds nothing woven by the warm-up
     */
    public function testItWarmsUpCacheIntoAnotherDirectory(): void
    {
        $otherCacheDir = $this->configuration['cacheDir'] . '-moved';
        $filesystem    = new Filesystem();
        $filesystem->remove($otherCacheDir);

        try {
            // Two workers: they build the other directory too, the command merges their records into it
            $output = $this->execute('cache:warmup:aop', ['--cache-dir=' . $otherCacheDir, '--workers=2']);
            $this->assertStringContainsString('Weaving in 2 parallel worker processes.', $output);

            $this->assertFileExists($otherCacheDir . '/_include.cache');
            $this->assertFileExists($otherCacheDir . '/src/Application/Main.php', 'Woven into the other directory');
            $this->assertFileDoesNotExist($this->configuration['cacheDir'] . '/src/Application/Main.php', 'Not woven into the directory of the kernel');

            // The "moved_cache" configuration runs on that directory as a prebuilt cache
            $phpExecutable = (new PhpExecutableFinder())->find();
            assert($phpExecutable !== false);
            $script = sprintf(
                'include %s; echo (new %s())->describe(second: 7), "|", %s::$calls;',
                var_export($this->configuration['frontController'], true),
                '\\' . EdgeCaseDemo::class,
                '\\' . EdgeCaseAspect::class,
            );
            $process = new Process([$phpExecutable, '-r', $script], null, ['GO_AOP_CONFIGURATION' => 'moved_cache']);
            $process->run();

            $this->assertTrue($process->isSuccessful(), $process->getOutput() . $process->getErrorOutput());
            $this->assertSame('1,7,3|1', trim($process->getOutput()), 'The advice ran: the class was served from the other directory');
        } finally {
            $filesystem->remove($otherCacheDir);
        }
    }
}
