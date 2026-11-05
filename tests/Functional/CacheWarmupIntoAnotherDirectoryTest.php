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

namespace Go\Functional;

use FilesystemIterator;
use Go\PhpUnit\UsesTemporaryDirectory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * A production kernel with a warm cache builds another cache directory exactly like a kernel with an empty cache
 *
 * In production the woven class map of the own cache is injected into the Composer loader: classes reflected by
 * name while weaving (ancestors, classes of property advices) must still resolve to their sources, not to the woven
 * files of the own cache (#807).
 */
class CacheWarmupIntoAnotherDirectoryTest extends BaseFunctionalTestCase
{
    use UsesTemporaryDirectory;

    private string $buildDir;

    public function setUp(): void
    {
        parent::setUp();
        $this->buildDir = self::createTemporaryDirectory('warmup-other-dir');
    }

    public function tearDown(): void
    {
        self::removeTemporaryDirectory($this->buildDir);
        parent::tearDown();
    }

    public function testWarmCacheOfProductionKernelBuildsTheSameDirectoryAsAnEmptyOne(): void
    {
        $this->assertFileExists($this->configuration['cacheDir'] . '/_include.cache', 'The own cache is warm');
        $fromWarmCache = $this->buildDir . '/from-warm-cache';
        $fromNoCache   = $this->buildDir . '/from-no-cache';

        $output = $this->execute('cache:warmup:aop', ['--cache-dir=' . $fromWarmCache]);
        $this->assertStringContainsString(', 0 errors', $output);

        $this->clearCache();
        $output = $this->execute('cache:warmup:aop', ['--cache-dir=' . $fromNoCache]);
        $this->assertStringContainsString(', 0 errors', $output);

        // A class with property advices is reflected by name: it is woven from its source
        $this->assertStringContainsString(
            'trait PromotedPropertyClassOriginalTrait',
            (string) file_get_contents($fromWarmCache . '/src/Application/PromotedPropertyClassOriginalTrait.php'),
        );
        $this->assertStringContainsString(
            'PromotedPropertyInterceptAspect::class',
            (string) file_get_contents($fromWarmCache . '/src/Application/PromotedPropertyClass.php'),
        );

        $this->assertSame($this->readCacheFiles($fromNoCache), $this->readCacheFiles($fromWarmCache));
        $this->assertSame(
            $this->readRecordKeys($fromNoCache),
            $this->readRecordKeys($fromWarmCache),
            'Both builds record the same sources',
        );
    }

    /**
     * Returns the content of every file of a cache directory, keyed by its path below the directory
     *
     * The metadata file is left out, and so is the stamp of the include map, its hash: the records carry the time of
     * the build.
     *
     * @return array<string, string>
     */
    private function readCacheFiles(string $cacheDir): array
    {
        $files    = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cacheDir, FilesystemIterator::SKIP_DOTS));
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            $relativePath = substr($file->getPathname(), strlen($cacheDir));
            if ($relativePath !== '/_transformation.cache') {
                $files[$relativePath] = (string) preg_replace(
                    "/^  'stamp' => '[0-9a-f]+',$/m",
                    "  'stamp' => '…',",
                    (string) file_get_contents($file->getPathname()),
                );
            }
        }
        ksort($files);

        return $files;
    }

    /**
     * @return list<string>
     */
    private function readRecordKeys(string $cacheDir): array
    {
        $metadata = include $cacheDir . '/_transformation.cache';
        $this->assertIsArray($metadata);
        $this->assertIsArray($metadata['files']);
        $recordKeys = array_map(strval(...), array_keys($metadata['files']));
        sort($recordKeys);

        return $recordKeys;
    }

    /**
     * The aspects are not woven, so their classes are loaded natively in both builds
     *
     * An aspect class autoloaded through the weaving pipeline (an empty own cache) makes the aspects register in
     * reverse order, while a warm own cache loads them natively, in registration order: advices of the same kind and
     * order of different aspects would swap places in the proxies (a known, separate issue). Without weaving the
     * aspects, both builds see the same aspect order and must write the same bytes.
     */
    protected function getConfigurationName(): string
    {
        return 'production_native_aspects';
    }
}
