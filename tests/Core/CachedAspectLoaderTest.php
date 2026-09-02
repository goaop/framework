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

namespace Go\Core;

use FilesystemIterator;
use Go\Aop\Advice;
use Go\Aop\Advisor;
use Go\Aop\Aspect;
use Go\Aop\Features;
use Go\Aop\Pointcut\TruePointcut;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class CachedAspectLoaderTest extends TestCase
{
    private string $appDir;

    private string $cacheDir;

    private Aspect $aspect;

    /**
     * Path of the fake aspect source file below the application root
     */
    private string $aspectFileName;

    /**
     * Advisor cache file shadowing the aspect source below the cache directory
     */
    private string $cacheFileName;

    /** @var AspectLoaderInterface&\PHPUnit\Framework\MockObject\MockObject */
    private AspectLoaderInterface $innerLoader;

    protected function setUp(): void
    {
        $baseDir        = sys_get_temp_dir() . '/goaop-cached-loader-' . uniqid();
        $this->appDir   = $baseDir . '/app';
        $this->cacheDir = $baseDir . '/cache';
        mkdir($this->appDir . '/src/Aspect', 0777, true);
        mkdir($this->cacheDir, 0777, true);

        // A uniquely-named fake aspect class living below the application root gives full
        // control over both the aspect source file and its shadow advisor cache file
        $shortClassName       = 'CachedLoaderTestAspect' . str_replace('.', '', uniqid('', true));
        $this->aspectFileName = $this->appDir . '/src/Aspect/' . $shortClassName . '.php';
        file_put_contents(
            $this->aspectFileName,
            "<?php\n\nnamespace Go\\Core\\TestFixture;\n\nclass {$shortClassName} implements \\Go\\Aop\\Aspect {}\n",
        );
        require $this->aspectFileName;
        $className = 'Go\\Core\\TestFixture\\' . $shortClassName;
        $aspect    = new $className();
        assert($aspect instanceof Aspect);
        $this->aspect = $aspect;

        // The naming scheme: app-root prefix replaced by the cache dir, '.php' by '.cache.php'
        $this->cacheFileName = $this->cacheDir . '/src/Aspect/' . $shortClassName . '.cache.php';
    }

    protected function tearDown(): void
    {
        // Guard the cleanup: deletions must stay inside the uniquely-named temp
        // directory this test created, whatever happens to the property value
        $this->assertStringStartsWith(sys_get_temp_dir() . '/goaop-cached-loader-', $this->appDir);
        $baseDir  = dirname($this->appDir);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $fileInfo) {
            assert($fileInfo instanceof SplFileInfo);
            $fileInfo->isDir() ? rmdir($fileInfo->getPathname()) : unlink($fileInfo->getPathname());
        }
        rmdir($baseDir);
    }

    private function createLoader(int $features): CachedAspectLoader
    {
        $this->innerLoader = $this->createMock(AspectLoaderInterface::class);
        $container         = $this->createMock(AspectContainer::class);
        $container->method('getService')->willReturn($this->innerLoader);

        return new CachedAspectLoader($container, AspectLoader::class, [
            'debug'          => false,
            'appDir'         => $this->appDir,
            'cacheDir'       => $this->cacheDir,
            'cacheFileMode'  => 0770,
            'features'       => $features,
            'includePaths'   => [],
            'excludePaths'   => [],
            'containerClass' => Container::class,
        ]);
    }

    /**
     * Writes an advisor cache file at the shadow path, optionally making it stale
     * (older than the aspect source file) by the freshness rules
     */
    private function writeCacheFile(string $content, bool $stale): void
    {
        mkdir(dirname($this->cacheFileName), 0777, true);
        file_put_contents($this->cacheFileName, $content);
        if ($stale) {
            $aspectModificationTime = filemtime($this->aspectFileName);
            $this->assertIsInt($aspectModificationTime);
            touch($this->cacheFileName, $aspectModificationTime - 100);
        }
        clearstatcache();
    }

    private function validCacheFileContent(): string
    {
        $version = AdvisorCacheCompiler::VERSION;

        return "<?php return ['version' => {$version}, 'advisors' => ['pc' => new \\Go\\Aop\\Pointcut\\TruePointcut()]];";
    }

    public function testPrebuiltCacheTrustsExistingCacheFileWithoutFreshnessChecks(): void
    {
        $loader = $this->createLoader(Features::PREBUILT_CACHE);
        // The cache file is deliberately STALE - the prebuilt mode must trust it anyway
        $this->writeCacheFile($this->validCacheFileContent(), stale: true);
        $this->innerLoader->expects($this->never())->method('load');

        $loadedItems = $loader->load($this->aspect);

        $this->assertArrayHasKey('pc', $loadedItems);
        $this->assertInstanceOf(TruePointcut::class, $loadedItems['pc']);
    }

    public function testPrebuiltCacheFallsBackToLoaderOnCorruptFileWithoutRewriting(): void
    {
        $loader = $this->createLoader(Features::PREBUILT_CACHE);
        // Truncated PHP raises a ParseError on include
        $this->writeCacheFile('<?php return [', stale: true);
        $this->innerLoader->expects($this->once())->method('load')->willReturn([]);

        $this->assertSame([], $loader->load($this->aspect));
        // The file system may be read-only under PREBUILT_CACHE - nothing may be written
        $this->assertSame('<?php return [', file_get_contents($this->cacheFileName));
    }

    public function testPrebuiltCacheFallsBackToLoaderOnWrongVersionWithoutRewriting(): void
    {
        $loader              = $this->createLoader(Features::PREBUILT_CACHE);
        $wrongVersionContent = "<?php return ['version' => 0, 'advisors' => ['pc' => new \\Go\\Aop\\Pointcut\\TruePointcut()]];";
        $this->writeCacheFile($wrongVersionContent, stale: true);
        $this->innerLoader->expects($this->once())->method('load')->willReturn([]);

        $this->assertSame([], $loader->load($this->aspect));
        $this->assertSame($wrongVersionContent, file_get_contents($this->cacheFileName));
    }

    public function testStaleCacheFileIsRebuiltAndRewritten(): void
    {
        $loader = $this->createLoader(0);
        $this->writeCacheFile($this->validCacheFileContent(), stale: true);
        $freshItems = ['pointcut.fresh' => new TruePointcut()];
        $this->innerLoader->expects($this->once())->method('load')->willReturn($freshItems);

        $this->assertSame($freshItems, $loader->load($this->aspect));

        // The stale file must have been replaced by a freshly compiled one that includes cleanly
        $rewrittenData = include $this->cacheFileName;
        $this->assertIsArray($rewrittenData);
        $this->assertSame(AdvisorCacheCompiler::VERSION, $rewrittenData['version']);
        $this->assertIsArray($rewrittenData['advisors']);
        $this->assertArrayHasKey('pointcut.fresh', $rewrittenData['advisors']);
        $this->assertInstanceOf(TruePointcut::class, $rewrittenData['advisors']['pointcut.fresh']);
    }

    public function testFreshButCorruptCacheFileIsRebuiltAndRewritten(): void
    {
        $loader = $this->createLoader(0);
        // Fresh by mtime, but not includable
        $this->writeCacheFile('<?php return [', stale: false);
        $freshItems = ['pointcut.fresh' => new TruePointcut()];
        $this->innerLoader->expects($this->once())->method('load')->willReturn($freshItems);

        $this->assertSame($freshItems, $loader->load($this->aspect));

        $rewrittenData = include $this->cacheFileName;
        $this->assertIsArray($rewrittenData);
        $this->assertSame(AdvisorCacheCompiler::VERSION, $rewrittenData['version']);
    }

    public function testFreshValidCacheFileIsUsedWithoutTouchingTheLoader(): void
    {
        $loader = $this->createLoader(0);
        $this->writeCacheFile($this->validCacheFileContent(), stale: false);
        $this->innerLoader->expects($this->never())->method('load');

        $loadedItems = $loader->load($this->aspect);

        $this->assertArrayHasKey('pc', $loadedItems);
        $this->assertInstanceOf(TruePointcut::class, $loadedItems['pc']);
    }

    public function testEntriesThatAreNeitherPointcutNorAdvisorAreFilteredOut(): void
    {
        $loader  = $this->createLoader(0);
        $version = AdvisorCacheCompiler::VERSION;
        $content = "<?php return ['version' => {$version}, 'advisors' => ["
            . "'pc' => new \\Go\\Aop\\Pointcut\\TruePointcut(), "
            . "'junk' => 'some string', "
            . "'number' => 42,"
            . "]];";
        $this->writeCacheFile($content, stale: false);
        $this->innerLoader->expects($this->never())->method('load');

        $loadedItems = $loader->load($this->aspect);

        $this->assertCount(1, $loadedItems);
        $this->assertArrayHasKey('pc', $loadedItems);
        $this->assertInstanceOf(TruePointcut::class, $loadedItems['pc']);
    }

    public function testAspectWithNotCompilableItemsIsNotCachedAtAll(): void
    {
        $loader               = $this->createLoader(0);
        $notCompilableAdvisor = new class implements Advisor {
            public function getAdvice(): Advice
            {
                throw new \LogicException('Not expected to be called');
            }
        };
        $loadedItems = ['custom.advisor' => $notCompilableAdvisor];
        $this->innerLoader->expects($this->once())->method('load')->willReturn($loadedItems);

        $this->assertSame($loadedItems, $loader->load($this->aspect));
        // Never a half-written file: compilation failure must skip the write entirely
        $this->assertFileDoesNotExist($this->cacheFileName);
    }

    public function testAspectOutsideApplicationRootIsLoadedDirectlyWithoutCaching(): void
    {
        $loader = $this->createLoader(0);
        // This test class itself lives outside the temp application root
        $foreignAspect = new class implements Aspect {};
        $loadedItems   = ['pc' => new TruePointcut()];
        $this->innerLoader->expects($this->once())->method('load')->willReturn($loadedItems);

        $this->assertSame($loadedItems, $loader->load($foreignAspect));

        // No shadow file may appear anywhere below the cache directory
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->cacheDir, FilesystemIterator::SKIP_DOTS),
        );
        $this->assertCount(0, iterator_to_array($iterator));
    }
}
