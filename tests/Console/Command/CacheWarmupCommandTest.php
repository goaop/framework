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
use Go\Tests\TestProject\Application\Main;

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
}
