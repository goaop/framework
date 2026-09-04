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

use Go\Aop\Features;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\Container;
use Go\Instrument\Transformer\SourceTransformer;
use Go\Instrument\Transformer\StreamMetaData;
use Go\Instrument\Transformer\TransformerResultEnum;
use PhpToken;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

// Separate processes: the loader holds process-wide static state (the registered
// stream filter and its cache collaborators), which every test configures differently
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class SourceTransformingLoaderTest extends TestCase
{
    private const ORIGINAL_SOURCE = "<?php echo 'original';\n";
    private const WOVEN_SOURCE    = "<?php echo 'woven';\n";

    private string $appDir;
    private string $cacheDir;
    private string $originalFile;

    /** @var AspectContainer&MockObject */
    private AspectContainer $container;

    private CachePathManager $cachePathManager;

    protected function setUp(): void
    {
        $this->appDir   = sys_get_temp_dir() . '/goaop-stl-app';
        $this->cacheDir = sys_get_temp_dir() . '/goaop-stl-cache';
        foreach ([$this->appDir . '/src', $this->cacheDir] as $directory) {
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
        }
        // The loader resolves the streamed path via realpath(), so the cache state
        // must be keyed by the resolved paths to match
        $this->appDir   = (string) realpath($this->appDir);
        $this->cacheDir = (string) realpath($this->cacheDir);

        $this->originalFile = $this->appDir . '/src/Some.php';
        file_put_contents($this->originalFile, self::ORIGINAL_SOURCE);
    }

    protected function tearDown(): void
    {
        // Deletes only the exact files this test writes, never a glob/recursive sweep:
        // a wrong directory value must not be able to erase anything else
        $this->assertStringStartsWith(sys_get_temp_dir() . '/goaop-stl-', $this->cacheDir);
        $knownFiles = [
            $this->cacheDir . '/src/Some.php',
            $this->cacheDir . '/src/Some' . AspectContainer::AOP_PROXIED_SUFFIX . '.php',
            $this->originalFile,
        ];
        foreach ($knownFiles as $knownFile) {
            if (is_file($knownFile)) {
                unlink($knownFile);
            }
        }
        @rmdir($this->cacheDir . '/src');
        @rmdir($this->cacheDir);
        @rmdir($this->appDir . '/src');
        @rmdir($this->appDir);
    }

    /**
     * Brings the loader up against a container mock, mirroring what ensureRegistered()
     * receives from a real kernel
     *
     * @param SourceTransformer[] $transformers Chain served by the interface tag query
     */
    private function registerLoader(array $transformers, int $features = 0, ?string $cacheDir = null): void
    {
        $kernel = $this->createMock(AspectKernel::class);
        $kernel->method('getOptions')->willReturn([
            'debug'          => true,
            'appDir'         => $this->appDir,
            'cacheDir'       => $cacheDir ?? $this->cacheDir,
            'cacheFileMode'  => 0770,
            'features'       => $features,
            'includePaths'   => [],
            'excludePaths'   => [],
            'containerClass' => Container::class,
        ]);
        $kernel->method('hasFeature')->willReturnCallback(
            static fn(int $featureToCheck): bool => ($features & $featureToCheck) !== 0,
        );

        $this->cachePathManager = new CachePathManager($kernel);

        $this->container = $this->createMock(AspectContainer::class);
        $this->container->method('getService')->willReturnMap([
            [AspectKernel::class, $kernel],
            [CachePathManager::class, $this->cachePathManager],
        ]);
        $this->container->method('getServicesByInterface')->willReturn($transformers);

        SourceTransformingLoader::ensureRegistered($this->container);
    }

    /**
     * Streams the original file through the registered filter, exactly like the
     * composer autoloader and the cache warmer do
     */
    private function filterOriginalFile(): string
    {
        $content = file_get_contents(
            SourceTransformingLoader::PHP_FILTER_READ
            . SourceTransformingLoader::getId()
            . '/resource=' . $this->originalFile,
        );
        $this->assertIsString($content);

        return $content;
    }

    /**
     * Creates a transformer stub that replaces the source and reports the given result
     */
    private function createTransformerStub(TransformerResultEnum $result, ?string $newSource = null): CountingSourceTransformerStub
    {
        return new CountingSourceTransformerStub($result, $newSource);
    }

    public function testFreshTransformedCacheRecordIsServedWithoutAnyTransformer(): void
    {
        $this->registerLoader([]);
        $this->container->expects($this->never())->method('getServicesByInterface');
        $this->container->method('isFreshSince')->willReturn(true);

        $cacheFile = $this->cacheDir . '/src/Some.php';
        mkdir(dirname($cacheFile), 0777, true);
        file_put_contents($cacheFile, self::WOVEN_SOURCE);
        $this->cachePathManager->setCacheState($this->originalFile, [
            'filemtime' => (int) filemtime($this->originalFile) + 10,
            'cacheUri'  => $cacheFile,
        ]);

        $this->assertSame(self::WOVEN_SOURCE, $this->filterOriginalFile());
    }

    public function testFreshUntransformedCacheRecordServesOriginalSourceWithoutAnyTransformer(): void
    {
        $this->registerLoader([]);
        $this->container->expects($this->never())->method('getServicesByInterface');
        $this->container->method('isFreshSince')->willReturn(true);

        $this->cachePathManager->setCacheState($this->originalFile, [
            'filemtime' => (int) filemtime($this->originalFile) + 10,
            'cacheUri'  => null,
        ]);

        $this->assertSame(self::ORIGINAL_SOURCE, $this->filterOriginalFile());
    }

    public function testCacheMissRunsTransformerChainAndPersistsWovenFile(): void
    {
        $transformer = $this->createTransformerStub(TransformerResultEnum::RESULT_TRANSFORMED, self::WOVEN_SOURCE);
        $this->registerLoader([$transformer]);

        $this->assertSame(self::WOVEN_SOURCE, $this->filterOriginalFile());

        $cacheFile = $this->cacheDir . '/src/Some.php';
        $this->assertFileExists($cacheFile);
        $this->assertSame(self::WOVEN_SOURCE, file_get_contents($cacheFile));
        $cacheState = $this->cachePathManager->queryCacheState($this->originalFile);
        $this->assertNotNull($cacheState);
        $this->assertSame($cacheFile, $cacheState['cacheUri']);
    }

    public function testWovenBodyTraitIsCachedUnderTheProxiedSuffix(): void
    {
        $wovenSource = "<?php\ntrait Some" . AspectContainer::AOP_PROXIED_SUFFIX . " { }\n";
        $transformer = $this->createTransformerStub(TransformerResultEnum::RESULT_TRANSFORMED, $wovenSource);
        $this->registerLoader([$transformer]);

        $this->assertSame($wovenSource, $this->filterOriginalFile());

        // The generated proxy claims the plain name in the cache, so the original body
        // trait has to move aside to its own sibling file
        $cacheFile = $this->cacheDir . '/src/Some' . AspectContainer::AOP_PROXIED_SUFFIX . '.php';
        $this->assertFileExists($cacheFile);
        $this->assertFileDoesNotExist($this->cacheDir . '/src/Some.php');
        $cacheState = $this->cachePathManager->queryCacheState($this->originalFile);
        $this->assertNotNull($cacheState);
        $this->assertSame($cacheFile, $cacheState['cacheUri']);
    }

    public function testTransformedSourceMerelyMentioningTheSuffixKeepsItsCacheFileName(): void
    {
        // Only a `trait <Name>Original` declaration marks a woven body; a class that just
        // carries the suffix word in its own name must not be moved aside
        $wovenSource = "<?php\nclass " . AspectContainer::AOP_PROXIED_SUFFIX . "Request { }\n";
        $transformer = $this->createTransformerStub(TransformerResultEnum::RESULT_TRANSFORMED, $wovenSource);
        $this->registerLoader([$transformer]);

        $this->assertSame($wovenSource, $this->filterOriginalFile());

        $cacheFile = $this->cacheDir . '/src/Some.php';
        $this->assertFileExists($cacheFile);
        $this->assertFileDoesNotExist($this->cacheDir . '/src/Some' . AspectContainer::AOP_PROXIED_SUFFIX . '.php');
        $cacheState = $this->cachePathManager->queryCacheState($this->originalFile);
        $this->assertNotNull($cacheState);
        $this->assertSame($cacheFile, $cacheState['cacheUri']);
    }

    public function testStaleCacheRecordFallsBackToTransformerChain(): void
    {
        $transformer = $this->createTransformerStub(TransformerResultEnum::RESULT_TRANSFORMED, self::WOVEN_SOURCE);
        $this->registerLoader([$transformer]);
        $this->container->method('isFreshSince')->willReturn(true);

        // The record is older than the original file => stale by the freshness rules
        $this->cachePathManager->setCacheState($this->originalFile, [
            'filemtime' => (int) filemtime($this->originalFile) - 100,
            'cacheUri'  => null,
        ]);

        $this->assertSame(self::WOVEN_SOURCE, $this->filterOriginalFile());
        $this->assertSame(1, $transformer->callCount);
    }

    public function testAbstainingChainRecordsFileAsUntransformedWithoutWritingCacheFile(): void
    {
        $transformer = $this->createTransformerStub(TransformerResultEnum::RESULT_ABSTAIN);
        $this->registerLoader([$transformer]);

        $this->assertSame(self::ORIGINAL_SOURCE, $this->filterOriginalFile());
        $this->assertSame(1, $transformer->callCount);

        $this->assertFileDoesNotExist($this->cacheDir . '/src/Some.php');
        $cacheState = $this->cachePathManager->queryCacheState($this->originalFile);
        $this->assertNotNull($cacheState);
        $this->assertNull($cacheState['cacheUri']);
    }

    public function testAbortingTransformerSkipsTheRestOfTheChain(): void
    {
        $aborting    = $this->createTransformerStub(TransformerResultEnum::RESULT_ABORTED);
        $neverCalled = $this->createTransformerStub(TransformerResultEnum::RESULT_TRANSFORMED, self::WOVEN_SOURCE);
        $this->registerLoader([$aborting, $neverCalled]);

        $this->assertSame(self::ORIGINAL_SOURCE, $this->filterOriginalFile());
        $this->assertSame(1, $aborting->callCount);
        $this->assertSame(0, $neverCalled->callCount);

        $this->assertFileDoesNotExist($this->cacheDir . '/src/Some.php');
    }

    public function testPrebuiltCacheTrustsStaleRecordWithoutFreshnessChecks(): void
    {
        $this->registerLoader([], Features::PREBUILT_CACHE);
        $this->container->expects($this->never())->method('getServicesByInterface');
        // Freshness collaborators must not even be consulted for a trusted record
        $this->container->expects($this->never())->method('isFreshSince');

        $cacheFile = $this->cacheDir . '/src/Some.php';
        mkdir(dirname($cacheFile), 0777, true);
        file_put_contents($cacheFile, self::WOVEN_SOURCE);
        // The record is deliberately STALE - the prebuilt mode must trust it anyway
        $this->cachePathManager->setCacheState($this->originalFile, [
            'filemtime' => 1,
            'cacheUri'  => $cacheFile,
        ]);

        $this->assertSame(self::WOVEN_SOURCE, $this->filterOriginalFile());
    }

    public function testSourcePassesThroughUntouchedWhenCachePathEqualsOriginal(): void
    {
        // With cacheDir == appDir the computed cache path equals the original file:
        // the guard must pass the source through without running any transformer
        $transformer = $this->createTransformerStub(TransformerResultEnum::RESULT_TRANSFORMED, self::WOVEN_SOURCE);
        $this->registerLoader([$transformer], 0, $this->appDir);

        $this->assertSame(self::ORIGINAL_SOURCE, $this->filterOriginalFile());
        $this->assertSame(0, $transformer->callCount);
        $this->assertNull($this->cachePathManager->queryCacheState($this->originalFile));
    }
}

/**
 * Transformer stub that replaces the source and counts its invocations
 */
final class CountingSourceTransformerStub implements SourceTransformer
{
    public int $callCount = 0;

    public function __construct(
        private readonly TransformerResultEnum $result,
        private readonly ?string $newSource,
    ) {}

    public function transform(StreamMetaData $metadata): TransformerResultEnum
    {
        $this->callCount++;
        if ($this->newSource !== null) {
            $metadata->setTokenStreamFromRawTokens(...PhpToken::tokenize($this->newSource));
        }

        return $this->result;
    }
}
