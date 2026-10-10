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

use Go\Aop\Exception\WeavingException;
use Go\Aop\Features;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\Container;
use Go\Instrument\Transformer\SourceTransformer;
use Go\Instrument\Transformer\StreamMetaData;
use Go\Instrument\Transformer\TransformerResult;
use Go\PhpUnit\UsesTemporaryDirectory;
use LogicException;
use PhpToken;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Go\Aop\WeavingDriver;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class SourceTransformingLoaderTest extends TestCase
{
    use UsesTemporaryDirectory;

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
        // The loader holds process-wide static state, which every test configures differently
        SourceTransformingLoader::reset();

        // Real directories: the stream filter includes woven sources and the loader resolves the
        // streamed path via realpath(), neither of which works on the virtual file system
        $this->appDir   = self::createTemporaryDirectory('stl-app');
        $this->cacheDir = self::createTemporaryDirectory('stl-cache');
        mkdir($this->appDir . '/src');

        $this->originalFile = $this->appDir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Some.php';
        file_put_contents($this->originalFile, self::ORIGINAL_SOURCE);
    }

    protected function tearDown(): void
    {
        SourceTransformingLoader::reset();
        self::removeTemporaryDirectory($this->cacheDir);
        self::removeTemporaryDirectory($this->appDir);
    }

    /**
     * Path below the cache directory with native separators, as the loader computes it
     */
    private function cachePath(string $relativePath): string
    {
        return $this->cacheDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
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
            'driver'         => WeavingDriver::Stream,
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
    private function createTransformerStub(TransformerResult $result, ?string $newSource = null): CountingSourceTransformerStub
    {
        return new CountingSourceTransformerStub($result, $newSource);
    }

    public function testFilterIdIsNotAvailableBeforeRegistration(): void
    {
        $this->expectException(WeavingException::class);
        $this->expectExceptionMessage('Stream filter was not registered');

        SourceTransformingLoader::getId();
    }

    public function testFilterCanNotBeRegisteredTwice(): void
    {
        $this->registerLoader([]);

        $this->expectException(WeavingException::class);
        $this->expectExceptionMessage('Stream filter already registered');

        SourceTransformingLoader::register();
    }

    public function testFilterNameTakenByAnotherFilterIsReported(): void
    {
        stream_filter_register('go.aop.taken', \php_user_filter::class);

        $this->expectException(WeavingException::class);
        $this->expectExceptionMessage('Stream filter was not registered');

        SourceTransformingLoader::register('go.aop.taken');
    }

    public function testResetForgetsRegistrationButReusesTheFilterRegisteredInPhp(): void
    {
        $this->registerLoader([]);
        $filterId = SourceTransformingLoader::getId();

        SourceTransformingLoader::reset();
        try {
            SourceTransformingLoader::getId();
            $this->fail('The filter id must be forgotten by reset()');
        } catch (WeavingException) {
        }

        // PHP can not unregister the filter: registering it again must reuse it
        $transformer = $this->createTransformerStub(TransformerResult::Transformed, self::WOVEN_SOURCE);
        $this->registerLoader([$transformer]);

        $this->assertSame($filterId, SourceTransformingLoader::getId());
        $this->assertSame(self::WOVEN_SOURCE, $this->filterOriginalFile());
    }

    public function testEarlyRegistrationIsConfiguredByTheKernel(): void
    {
        // A library registering the filter before the kernel boots must not disable weaving
        SourceTransformingLoader::register();

        $transformer = $this->createTransformerStub(TransformerResult::Transformed, self::WOVEN_SOURCE);
        $this->registerLoader([$transformer]);

        $this->assertSame(self::WOVEN_SOURCE, $this->filterOriginalFile());
        $this->assertSame(1, $transformer->callCount);
    }

    public function testTransformerFailureNamesTheTransformerAndTheFile(): void
    {
        $failingTransformer = new FailingSourceTransformerStub();
        $this->registerLoader([$failingTransformer]);
        $stream = fopen($this->originalFile, 'rb');
        $this->assertIsResource($stream);
        $metadata = new StreamMetaData($stream, self::ORIGINAL_SOURCE);

        try {
            SourceTransformingLoader::transformCode($metadata);
            $this->fail('The transformer failure must be reported');
        } catch (WeavingException $exception) {
            $this->assertSame(
                FailingSourceTransformerStub::class . ' failed to transform ' . $metadata->uri . ': Broken transformer',
                $exception->getMessage(),
            );
            $this->assertInstanceOf(LogicException::class, $exception->getPrevious());
        }
    }

    public function testFreshTransformedCacheRecordIsServedWithoutAnyTransformer(): void
    {
        $this->registerLoader([]);
        $this->container->expects($this->never())->method('getServicesByInterface');
        $this->container->method('isFreshSince')->willReturn(true);

        $cacheFile = $this->cachePath('src/Some.php');
        mkdir(dirname($cacheFile), 0777, true);
        file_put_contents($cacheFile, self::WOVEN_SOURCE);
        $this->cachePathManager->setCacheState($this->originalFile, $this->freshRecord($cacheFile));

        $this->assertSame(self::WOVEN_SOURCE, $this->filterOriginalFile());
    }

    public function testFreshUntransformedCacheRecordServesOriginalSourceWithoutAnyTransformer(): void
    {
        $this->registerLoader([]);
        $this->container->expects($this->never())->method('getServicesByInterface');
        $this->container->method('isFreshSince')->willReturn(true);

        $this->cachePathManager->setCacheState($this->originalFile, $this->freshRecord(null));

        $this->assertSame(self::ORIGINAL_SOURCE, $this->filterOriginalFile());
    }

    public function testCacheMissRunsTransformerChainAndPersistsWovenFile(): void
    {
        $transformer = $this->createTransformerStub(TransformerResult::Transformed, self::WOVEN_SOURCE);
        $this->registerLoader([$transformer]);

        $this->assertSame(self::WOVEN_SOURCE, $this->filterOriginalFile());

        $cacheFile = $this->cachePath('src/Some.php');
        $this->assertFileExists($cacheFile);
        $this->assertSame(self::WOVEN_SOURCE, file_get_contents($cacheFile));
        $cacheState = $this->cachePathManager->queryCacheState($this->originalFile);
        $this->assertNotNull($cacheState);
        $this->assertSame($cacheFile, $cacheState['cacheUri']);
    }

    public function testWovenBodyTraitIsCachedUnderTheProxiedSuffix(): void
    {
        $wovenSource = "<?php\ntrait Some" . AspectContainer::ORIGINAL_TRAIT_SUFFIX . " { }\n";
        $transformer = $this->createTransformerStub(TransformerResult::Transformed, $wovenSource);
        $this->registerLoader([$transformer]);

        $this->assertSame($wovenSource, $this->filterOriginalFile());

        // The generated proxy claims the plain name in the cache, so the original body
        // trait has to move aside to its own sibling file
        $cacheFile = $this->cachePath('src/Some' . AspectContainer::ORIGINAL_TRAIT_FILE_SUFFIX);
        $this->assertFileExists($cacheFile);
        $this->assertFileDoesNotExist($this->cachePath('src/Some.php'));
        $cacheState = $this->cachePathManager->queryCacheState($this->originalFile);
        $this->assertNotNull($cacheState);
        $this->assertSame($cacheFile, $cacheState['cacheUri']);
    }

    public function testTransformedSourceMerelyMentioningTheSuffixKeepsItsCacheFileName(): void
    {
        // Only a `trait <Name>OriginalTrait` declaration marks a woven body; a class that just
        // carries the suffix word in its own name must not be moved aside
        $wovenSource = "<?php\nclass " . AspectContainer::ORIGINAL_TRAIT_SUFFIX . "Request { }\n";
        $transformer = $this->createTransformerStub(TransformerResult::Transformed, $wovenSource);
        $this->registerLoader([$transformer]);

        $this->assertSame($wovenSource, $this->filterOriginalFile());

        $cacheFile = $this->cachePath('src/Some.php');
        $this->assertFileExists($cacheFile);
        $this->assertFileDoesNotExist($this->cachePath('src/Some' . AspectContainer::ORIGINAL_TRAIT_FILE_SUFFIX));
        $cacheState = $this->cachePathManager->queryCacheState($this->originalFile);
        $this->assertNotNull($cacheState);
        $this->assertSame($cacheFile, $cacheState['cacheUri']);
    }

    /**
     * Cache record matching the current original file
     *
     * @return array{filemtime: int|false, filesize: int|false, cachedAt: int, cacheUri: string|null}
     */
    private function freshRecord(?string $cacheUri): array
    {
        clearstatcache();

        return [
            'filemtime' => filemtime($this->originalFile),
            'filesize'  => filesize($this->originalFile),
            'cachedAt'  => time(),
            'cacheUri'  => $cacheUri,
        ];
    }

    public function testSourceWithAnOlderMtimeIsWovenAgain(): void
    {
        $transformer = $this->createTransformerStub(TransformerResult::Transformed, self::WOVEN_SOURCE);
        $this->registerLoader([$transformer]);
        $this->container->method('isFreshSince')->willReturn(true);
        $this->cachePathManager->setCacheState($this->originalFile, $this->freshRecord(null));

        // A deployment restores an older mtime of a changed source (rsync -t, checkout of an older revision)
        touch($this->originalFile, (int) filemtime($this->originalFile) - 3600);
        clearstatcache();

        $this->assertSame(self::WOVEN_SOURCE, $this->filterOriginalFile());
        $this->assertSame(1, $transformer->callCount);
    }

    public function testSourceWithAnotherSizeIsWovenAgain(): void
    {
        $transformer = $this->createTransformerStub(TransformerResult::Transformed, self::WOVEN_SOURCE);
        $this->registerLoader([$transformer]);
        $this->container->method('isFreshSince')->willReturn(true);
        $record = $this->freshRecord(null);
        $this->cachePathManager->setCacheState($this->originalFile, $record);

        // Same mtime, different content length
        file_put_contents($this->originalFile, self::ORIGINAL_SOURCE . "// changed\n");
        touch($this->originalFile, (int) $record['filemtime']);
        clearstatcache();

        $this->assertSame(self::WOVEN_SOURCE, $this->filterOriginalFile());
        $this->assertSame(1, $transformer->callCount);
    }

    public function testStaleCacheRecordFallsBackToTransformerChain(): void
    {
        $transformer = $this->createTransformerStub(TransformerResult::Transformed, self::WOVEN_SOURCE);
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
        $transformer = $this->createTransformerStub(TransformerResult::Abstain);
        $this->registerLoader([$transformer]);

        $this->assertSame(self::ORIGINAL_SOURCE, $this->filterOriginalFile());
        $this->assertSame(1, $transformer->callCount);

        $this->assertFileDoesNotExist($this->cachePath('src/Some.php'));
        $cacheState = $this->cachePathManager->queryCacheState($this->originalFile);
        $this->assertNotNull($cacheState);
        $this->assertNull($cacheState['cacheUri']);
    }

    public function testAbortingTransformerSkipsTheRestOfTheChain(): void
    {
        $aborting    = $this->createTransformerStub(TransformerResult::Aborted);
        $neverCalled = $this->createTransformerStub(TransformerResult::Transformed, self::WOVEN_SOURCE);
        $this->registerLoader([$aborting, $neverCalled]);

        $this->assertSame(self::ORIGINAL_SOURCE, $this->filterOriginalFile());
        $this->assertSame(1, $aborting->callCount);
        $this->assertSame(0, $neverCalled->callCount);

        $this->assertFileDoesNotExist($this->cachePath('src/Some.php'));
    }

    public function testAbortedChainRevertsChangesOfEarlierTransformers(): void
    {
        $transforming = $this->createTransformerStub(TransformerResult::Transformed, self::WOVEN_SOURCE);
        $aborting     = $this->createTransformerStub(TransformerResult::Aborted);
        $this->registerLoader([$transforming, $aborting]);

        $this->assertSame(self::ORIGINAL_SOURCE, $this->filterOriginalFile());
        $this->assertSame(1, $aborting->callCount);

        $this->assertFileDoesNotExist($this->cachePath('src/Some.php'));
        $cacheState = $this->cachePathManager->queryCacheState($this->originalFile);
        $this->assertNotNull($cacheState);
        $this->assertNull($cacheState['cacheUri']);
    }

    public function testPrebuiltCacheTrustsStaleRecordWithoutFreshnessChecks(): void
    {
        $this->registerLoader([], Features::PREBUILT_CACHE);
        $this->container->expects($this->never())->method('getServicesByInterface');
        // Freshness collaborators must not even be consulted for a trusted record
        $this->container->expects($this->never())->method('isFreshSince');

        $cacheFile = $this->cachePath('src/Some.php');
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
        $transformer = $this->createTransformerStub(TransformerResult::Transformed, self::WOVEN_SOURCE);
        $this->registerLoader([$transformer], 0, $this->appDir);

        $this->assertSame(self::ORIGINAL_SOURCE, $this->filterOriginalFile());
        $this->assertSame(0, $transformer->callCount);
        $this->assertNull($this->cachePathManager->queryCacheState($this->originalFile));
    }
}

/**
 * Transformer stub that always fails
 */
final class FailingSourceTransformerStub implements SourceTransformer
{
    public function transform(StreamMetaData $metadata): TransformerResult
    {
        throw new LogicException('Broken transformer');
    }
}

/**
 * Transformer stub that replaces the source and counts its invocations
 */
final class CountingSourceTransformerStub implements SourceTransformer
{
    public int $callCount = 0;

    public function __construct(
        private readonly TransformerResult $result,
        private readonly ?string $newSource,
    ) {}

    public function transform(StreamMetaData $metadata): TransformerResult
    {
        $this->callCount++;
        if ($this->newSource !== null) {
            $metadata->setTokenStreamFromRawTokens(...PhpToken::tokenize($this->newSource));
        }

        return $this->result;
    }
}
