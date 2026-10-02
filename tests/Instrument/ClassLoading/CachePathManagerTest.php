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

namespace Go\Instrument\ClassLoading;

use Go\Aop\Exception\InvalidConfigurationException;
use Go\Aop\Exception\WeavingException;
use Go\Core\AspectKernel;
use Go\Core\Container;
use Go\PhpUnit\UsesTemporaryDirectory;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

// Separate processes: the cache files reference the AOP_ROOT_DIR/AOP_CACHE_DIR constants,
// which other tests in the shared process define with the fixture-project paths
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class CachePathManagerTest extends TestCase
{
    use UsesTemporaryDirectory;

    private static string $appDir;
    private static string $cacheDir;

    public static function setUpBeforeClass(): void
    {
        // Real directories: the cache files are loaded with include and reference the AOP_ROOT_DIR and
        // AOP_CACHE_DIR constants, which must match the directories of every test in this class exactly
        // (each test runs in its own process, so the constants are defined once per process)
        self::$appDir   = self::createTemporaryDirectory('cpm-app');
        self::$cacheDir = self::createTemporaryDirectory('cpm-cache');
        if (!defined('AOP_ROOT_DIR')) {
            define('AOP_ROOT_DIR', self::$appDir);
            define('AOP_CACHE_DIR', self::$cacheDir);
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::removeTemporaryDirectory(self::$cacheDir);
        self::removeTemporaryDirectory(self::$appDir);
    }

    private function createManager(bool $prebuiltCache = false, ?string $cacheDir = null): CachePathManager
    {
        $kernel = $this->createMock(AspectKernel::class);
        $kernel->method('getOptions')->willReturn([
            'debug'          => false,
            'appDir'         => self::$appDir,
            'cacheDir'       => $cacheDir ?? self::$cacheDir,
            'cacheFileMode'  => 0770,
            'features'       => 0,
            'includePaths'   => [],
            'excludePaths'   => [],
            'containerClass' => Container::class,
        ]);
        $kernel->method('hasFeature')->willReturn($prebuiltCache);

        return new CachePathManager($kernel);
    }

    public function testFlushWritesBothFilesAndClassMapLoadsWithoutFullMetadata(): void
    {
        $original    = self::$appDir . '/src/Some.php';
        $transformed = self::$cacheDir . '/src/Some.php';
        $known       = self::$appDir . '/src/Untransformed.php';

        $writer = $this->createManager();
        // @phpstan-ignore argument.type (fixture class name that is never loaded)
        $writer->registerClassForResource($original, 'App\Some');
        $writer->setCacheState($original, ['filemtime' => 12345, 'cacheUri' => $transformed]);
        // @phpstan-ignore argument.type (fixture class name that is never loaded)
        $writer->registerClassForResource($known, 'App\Untransformed');
        $writer->setCacheState($known, ['filemtime' => 12345, 'cacheUri' => null]);
        $writer->flushCacheState();

        $this->assertFileExists(self::$cacheDir . '/_transformation.cache');
        $this->assertFileExists(self::$cacheDir . '/_include.cache');

        $reader = $this->createManager();
        // The runtime class map and skip set are available immediately...
        // @phpstan-ignore method.impossibleType (fixture class name that is never loaded)
        $this->assertSame(['App\Some' => $transformed], $reader->queryClassMap());
        // @phpstan-ignore method.impossibleType (fixture class name that is never loaded)
        $this->assertSame(['App\Untransformed' => true], $reader->querySkippedClasses());
        // ...while the full metadata was not materialized yet (loaded lazily on demand)
        $loadedFlag = new ReflectionProperty(CachePathManager::class, 'cacheStateLoaded');
        $this->assertFalse($loadedFlag->getValue($reader), 'Full metadata should not be loaded eagerly');

        $this->assertSame(
            ['filemtime' => 12345, 'cacheUri' => $transformed, 'classes' => ['App\Some']],
            $reader->queryCacheState($original),
        );
        $this->assertTrue($loadedFlag->getValue($reader));
    }

    public function testLegacyCacheDirectoryWithoutClassMapIsTreatedAsStale(): void
    {
        $original    = self::$appDir . '/src/Legacy.php';
        $transformed = self::$cacheDir . '/src/Legacy.php';

        $writer = $this->createManager();
        $writer->setCacheState($original, ['filemtime' => 777, 'cacheUri' => $transformed]);
        $writer->flushCacheState();
        unlink(self::$cacheDir . '/_include.cache');

        // Pre-class-map cache directories carry no class names, so the metadata is
        // ignored entirely: everything re-weaves once and both files are rewritten
        $reader = $this->createManager();
        $this->assertSame([], $reader->queryClassMap());
        $this->assertSame([], $reader->querySkippedClasses());
        $this->assertNull($reader->queryCacheState($original));
    }

    public function testMetadataFilesCarryTheFormatVersion(): void
    {
        $writer = $this->createManager();
        $writer->setCacheState(self::$appDir . '/src/Versioned.php', ['filemtime' => 1, 'cacheUri' => null]);
        $writer->flushCacheState();

        foreach (['/_transformation.cache', '/_include.cache'] as $fileName) {
            $data = include self::$cacheDir . $fileName;
            $this->assertIsArray($data);
            $this->assertSame(CachePathManager::FORMAT_VERSION, $data['version'] ?? null, $fileName);
        }
    }

    public function testMetadataOfAnotherFormatVersionIsIgnored(): void
    {
        $original = self::$appDir . '/src/Outdated.php';
        file_put_contents(self::$cacheDir . '/_include.cache', "<?php return ['map' => ['App\\Outdated' => 'x'], 'skip' => []];");
        file_put_contents(self::$cacheDir . '/_transformation.cache', "<?php return ['" . $original . "' => ['cacheUri' => 'x']];");

        $reader = $this->createManager();

        $this->assertSame([], $reader->queryClassMap());
        $this->assertNull($reader->queryCacheState($original));
    }

    public function testPrebuiltCacheRejectsMetadataOfAnotherFormatVersion(): void
    {
        file_put_contents(self::$cacheDir . '/_include.cache', "<?php return ['map' => [], 'skip' => []];");

        $this->expectException(WeavingException::class);
        $this->expectExceptionMessage('rebuild it with `bin/aspect cache:warmup:aop`');

        $this->createManager(prebuiltCache: true);
    }

    public function testPrebuiltCacheRejectsTransformationMetadataOfAnotherFormatVersion(): void
    {
        $original = self::$appDir . '/src/Outdated.php';
        $writer   = $this->createManager();
        $writer->setCacheState($original, ['filemtime' => 1, 'cacheUri' => null]);
        $writer->flushCacheState();
        file_put_contents(self::$cacheDir . '/_transformation.cache', "<?php return ['" . $original . "' => ['cacheUri' => 'x']];");

        // The class map is current, so the outdated full metadata is only detected once it is loaded on demand
        $reader = $this->createManager(prebuiltCache: true);

        $this->expectException(WeavingException::class);
        $this->expectExceptionMessage('rebuild it with `bin/aspect cache:warmup:aop`');

        $reader->queryCacheState($original);
    }

    public function testCacheDirectoryWithMissingParentIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Can not create a directory');

        $this->createManager(cacheDir: self::$appDir . '/missing/parent/cache');
    }

    public function testCachePathReplacesOnlyTheLeadingApplicationDirectory(): void
    {
        $manager = $this->createManager();

        // A nested directory repeating the application path keeps its name
        $nested = self::$appDir . '/vendor' . self::$appDir . '/Foo.php';
        $this->assertSame(
            self::$cacheDir . '/vendor' . self::$appDir . '/Foo.php',
            $manager->getCachePathForResource($nested),
        );
        // A sibling sharing the name prefix of the application directory is not below it
        $sibling = self::$appDir . '-old/Foo.php';
        $this->assertSame($sibling, $manager->getCachePathForResource($sibling));
    }
}
