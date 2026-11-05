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

namespace Go\Instrument\ClassLoading\Opcache;

use Go\VirtualFileSystem\FileSystem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What can be checked without a file cache: the PHPUnit process has none, priming itself is covered by the functional
 * OpcacheWarmupCommandTest
 */
class FileCachePrimerTest extends TestCase
{
    public function testTheTestProcessCanNotPrimeAFileCache(): void
    {
        $problem = FileCachePrimer::configurationProblem();

        $this->assertNotNull($problem);
        $this->assertStringContainsString('opcache', $problem);
    }

    /**
     * @return array<string, array{array<string, string|false>, string|null, string|null}>
     */
    public static function fileCacheSettings(): array
    {
        $dir = sys_get_temp_dir();

        return [
            'all set'                        => [['opcache.enable_cli' => '1', 'opcache.file_cache' => $dir, 'opcache.file_cache_only' => '1', 'opcache.file_cache_read_only' => '0'], $dir, null],
            'no read-only setting (PHP 8.4)' => [['opcache.enable_cli' => 'On', 'opcache.file_cache' => $dir, 'opcache.file_cache_only' => 'On'], null, null],
            'cli off'                        => [['opcache.enable_cli' => '0', 'opcache.file_cache' => $dir, 'opcache.file_cache_only' => '1'], null, 'opcache.enable_cli is off'],
            'cli unset'                      => [['opcache.file_cache' => $dir], null, 'opcache.enable_cli is off'],
            'no file cache'                  => [['opcache.enable_cli' => '1', 'opcache.file_cache' => '', 'opcache.file_cache_only' => '1'], null, 'opcache.file_cache is not set'],
            'missing directory'              => [['opcache.enable_cli' => '1', 'opcache.file_cache' => $dir . '/goaop-missing-file-cache', 'opcache.file_cache_only' => '1'], null, "the opcache.file_cache directory {$dir}/goaop-missing-file-cache does not exist"],
            'other directory'                => [['opcache.enable_cli' => '1', 'opcache.file_cache' => $dir, 'opcache.file_cache_only' => '1'], __DIR__, "opcache.file_cache is {$dir}, not " . __DIR__],
            'not file cache only'            => [['opcache.enable_cli' => '1', 'opcache.file_cache' => $dir, 'opcache.file_cache_only' => '0'], $dir, 'opcache.file_cache_only is off'],
            'read only (PHP 8.5)'            => [['opcache.enable_cli' => '1', 'opcache.file_cache' => $dir, 'opcache.file_cache_only' => '1', 'opcache.file_cache_read_only' => '1'], null, 'opcache.file_cache_read_only is on'],
        ];
    }

    /**
     * @param array<string, string|false> $settings
     */
    #[DataProvider('fileCacheSettings')]
    public function testConfigurationProblemNamesTheFirstSettingThatKeepsScriptsOutOfTheFileCache(array $settings, ?string $fileCacheDir, ?string $expectedProblem): void
    {
        $this->assertSame($expectedProblem, FileCachePrimer::configurationProblem($fileCacheDir, $settings));
    }

    public function testConfigurationProblemNamesAnUnwritableDirectory(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('root writes everywhere');
        }
        $fileSystem = FileSystem::mount('primerrovfs');
        try {
            $dir = $fileSystem->path('/readonly');
            mkdir($dir, 0555);

            $this->assertSame(
                "the opcache.file_cache directory {$dir} is not writable",
                FileCachePrimer::configurationProblem(null, ['opcache.enable_cli' => '1', 'opcache.file_cache' => $dir, 'opcache.file_cache_only' => '1']),
            );
        } finally {
            $fileSystem->unmount();
        }
    }

    public function testCommandLineCarriesTheFileCacheSettingsAndTheArguments(): void
    {
        $commandLine = FileCachePrimer::commandLine('/var/cache/opcache', ['bin/aspect', 'cache:warmup:opcache', 'web/index.php']);

        $this->assertStringStartsWith('mkdir -p /var/cache/opcache && ' . PHP_BINARY . ' -d opcache.enable_cli=1 -d opcache.file_cache_only=1 -d opcache.file_cache=/var/cache/opcache', $commandLine);
        $this->assertStringEndsWith(' bin/aspect cache:warmup:opcache web/index.php', $commandLine);
    }

    public function testCommandLineNamesAPlaceholderWithoutAFileCacheDirectory(): void
    {
        $this->assertStringContainsString('-d opcache.file_cache=<directory> ', FileCachePrimer::commandLine(null, ['bin/aspect']));
    }

    public function testCacheFilesListsEveryFileBelowTheCacheDirectory(): void
    {
        $fileSystem = FileSystem::mount('primervfs');
        try {
            $cacheDir = $fileSystem->path('/cache');
            mkdir($cacheDir . '/src/Service', 0777, true);
            file_put_contents($cacheDir . '/_include.cache', '<?php return [];');
            file_put_contents($cacheDir . '/src/Service/Order.php', '<?php');
            file_put_contents($cacheDir . '/src/Service/OrderOriginalTrait.php', '<?php');

            $this->assertSame(
                [$cacheDir . '/_include.cache', $cacheDir . '/src/Service/Order.php', $cacheDir . '/src/Service/OrderOriginalTrait.php'],
                FileCachePrimer::cacheFiles($cacheDir),
            );
            $this->assertSame([], FileCachePrimer::cacheFiles($cacheDir . '/missing'));
        } finally {
            $fileSystem->unmount();
        }
    }

    /**
     * Files this process included already compile again (class declarations are no compile-time conflict), paths are
     * resolved first, a missing file is reported. Not covered here: an included file that declares functions, whose
     * compile error bails out of the engine and leaves user stream filters broken for the rest of the process
     * (a PHP quirk that would break the stream filter tests running after this one)
     */
    public function testIncludedFilesCompileAndFailuresAreReported(): void
    {
        $results  = [];
        $compiled = new FileCachePrimer()->compile(
            [__FILE__, __DIR__ . '/../../../../vendor/../vendor/autoload.php', __DIR__ . '/does-not-exist.php'],
            static function (string $file, ?string $error) use (&$results): void {
                $results[$file] = $error;
            },
        );

        $this->assertSame(2, $compiled);
        $this->assertNull($results[__FILE__]);
        $this->assertNull($results[__DIR__ . '/../../../../vendor/../vendor/autoload.php'], 'Resolved to the included file');
        $this->assertIsString($results[__DIR__ . '/does-not-exist.php']);
    }

    /**
     * A syntax error is thrown by opcache_compile_file() (no engine bailout) and reported with the file
     */
    public function testSyntaxErrorIsReportedWithTheFile(): void
    {
        $fileSystem = FileSystem::mount('primersyntaxvfs');
        try {
            $file = $fileSystem->path('/broken.php');
            file_put_contents($file, "<?php class {\n");
            $results  = [];
            $compiled = new FileCachePrimer()->compile([$file], static function (string $file, ?string $error) use (&$results): void {
                $results[$file] = $error;
            });

            $this->assertSame(0, $compiled);
            $this->assertIsString($results[$file]);
            if (filter_var(ini_get('opcache.enable_cli'), FILTER_VALIDATE_BOOL)) {
                $this->assertStringStartsWith('ParseError: ', $results[$file]);
            }
        } finally {
            $fileSystem->unmount();
        }
    }
}
