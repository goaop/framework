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

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Intercepted includes of files known to the cache are served natively in production (issue #746)
 *
 * An untransformed file is included from its original location, a woven file from its cached file: no
 * `php://filter` include (opcache never caches those) and no load of the full `_transformation.cache`.
 */
class IncludeInterceptionTest extends BaseFunctionalTestCase
{
    private const string MOVED_CONFIGURATION = 'moved_includes_cache';

    private string $includesDir;

    private string $movedCacheDir;

    public function setUp(): void
    {
        parent::setUp();
        $includesDir = realpath(__DIR__ . '/../Fixtures/project/src/Application/includes');
        $this->assertIsString($includesDir);
        $this->includesDir   = $includesDir;
        $this->movedCacheDir = $this->configuration['cacheDir'] . '-moved';
        (new Filesystem())->remove($this->movedCacheDir);
    }

    public function tearDown(): void
    {
        (new Filesystem())->remove($this->movedCacheDir);
        parent::tearDown();
    }

    public function testKnownFilesAreIncludedNativelyOnEveryRequest(): void
    {
        $cacheDir = realpath($this->configuration['cacheDir']);
        $this->assertIsString($cacheDir);

        // The cache was warmed up in setUp(): both requests are served from the cache
        $this->assertIncludedNatively($cacheDir, $this->runScript($this->getConfigurationName()));
        $this->assertIncludedNatively($cacheDir, $this->runScript($this->getConfigurationName()));
    }

    public function testKnownFilesAreIncludedNativelyFromMovedPrebuiltCache(): void
    {
        (new Filesystem())->mirror($this->configuration['cacheDir'], $this->movedCacheDir);
        // Without the original cache only the moved copy can serve the woven files
        $this->clearCache();
        $movedCacheDir = realpath($this->movedCacheDir);
        $this->assertIsString($movedCacheDir);

        $this->assertIncludedNatively($movedCacheDir, $this->runScript(self::MOVED_CONFIGURATION));
        $this->assertDirectoryDoesNotExist($this->configuration['cacheDir'], 'Nothing may be woven again');
    }

    protected function getConfigurationName(): string
    {
        return 'production_includes';
    }

    /**
     * @param array<mixed> $output
     */
    private function assertIncludedNatively(string $cacheDir, array $output): void
    {
        $plainFile = $this->includesDir . DIRECTORY_SEPARATOR . 'plain-file.php';
        $wovenFile = $this->includesDir . DIRECTORY_SEPARATOR . 'woven-file.php';
        // Magic constants resolve to the original location, wherever the file was included from
        $this->assertSame(
            ['plain' => ['file' => $plainFile], 'woven' => ['file' => $wovenFile, 'plain' => ['file' => $plainFile]]],
            $output['result'] ?? null,
        );

        $cachedWovenFile = $cacheDir . '/src/Application/includes/woven-file.php';
        $this->assertSame(
            ['plain-file.php' => $plainFile, 'woven-file.php' => $cachedWovenFile],
            $output['rewrites'] ?? null,
            'Known files are included natively, the untransformed one from its location, not through the filter',
        );

        $includedFiles = $output['includedFiles'] ?? null;
        $this->assertIsArray($includedFiles);
        $this->assertContains($plainFile, $includedFiles);
        $this->assertContains($cachedWovenFile, $includedFiles, 'The woven file is included from its cached file');
        $this->assertNotContains($wovenFile, $includedFiles);
        foreach ($includedFiles as $includedFile) {
            $this->assertIsString($includedFile);
            $this->assertStringEndsNotWith('_transformation.cache', $includedFile, 'The full metadata is not needed');
        }
    }

    /**
     * @return array<mixed>
     */
    private function runScript(string $configurationName): array
    {
        $phpExecutable = (new PhpExecutableFinder())->find();
        $this->assertIsString($phpExecutable);
        $process = new Process(
            [$phpExecutable, __DIR__ . '/../Fixtures/project/bin/includes.php'],
            null,
            ['GO_AOP_CONFIGURATION' => $configurationName],
        );
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getOutput() . $process->getErrorOutput());

        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($result);

        return $result;
    }
}
