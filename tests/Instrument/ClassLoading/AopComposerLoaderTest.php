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

use Composer\Autoload\ClassLoader;
use Go\Core\AspectContainer;
use Go\Core\Container;
use Go\Core\AspectKernel;
use Go\Instrument\FileSystem\Enumerator;
use Go\Instrument\Transformer\FilterInjectorTransformer;
use Go\PhpUnit\UsesTemporaryDirectory;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use SplFileInfo;

class AopComposerLoaderTest extends TestCase
{
    use UsesTemporaryDirectory;

    private string $appDir;

    protected function setUp(): void
    {
        // Real directory: the file enumerator resolves candidate files with realpath()
        $this->appDir = self::createTemporaryDirectory('composer-loader');
        mkdir($this->appDir . '/vendor/goaop/dissect/src', 0777, true);
        mkdir($this->appDir . '/src', 0777, true);
        touch($this->appDir . '/vendor/goaop/dissect/src/Parser.php');
        touch($this->appDir . '/src/Service.php');
    }

    protected function tearDown(): void
    {
        FilterInjectorTransformer::reset();
        self::removeTemporaryDirectory($this->appDir);
    }

    public function testExcludedPathsAreNotWoven(): void
    {
        $loader = new AopComposerLoader(new ClassLoader(), $this->createContainer(), [
            'debug'          => true,
            'appDir'         => $this->appDir,
            'cacheDir'       => null,
            'cacheFileMode'  => 0770,
            'features'       => 0,
            'includePaths'   => [],
            'excludePaths'   => [$this->appDir . '/vendor/goaop/dissect'],
            'containerClass' => Container::class,
        ]);

        $fileEnumerator = new ReflectionProperty(AopComposerLoader::class, 'fileEnumerator')->getValue($loader);
        $this->assertInstanceOf(Enumerator::class, $fileEnumerator);
        $isAllowed = $fileEnumerator->getFilter();

        $this->assertFalse($isAllowed(new SplFileInfo($this->appDir . '/vendor/goaop/dissect/src/Parser.php')));
        $this->assertTrue($isAllowed(new SplFileInfo($this->appDir . '/src/Service.php')));
    }

    public function testLoadClassIncludesFileInIsolatedScope(): void
    {
        $className = __NAMESPACE__ . '\\IsolatedScopeProbe';
        $probeFile = $this->appDir . '/lib/IsolatedScopeProbe.php';
        mkdir(dirname($probeFile));
        file_put_contents($probeFile, <<<'PHP'
            <?php
            namespace Go\Instrument\ClassLoading;

            final class IsolatedScopeProbe
            {
                public static bool $sawThis;
            }
            IsolatedScopeProbe::$sawThis = isset($this);
            PHP);
        $composerLoader = new ClassLoader();
        $composerLoader->addClassMap([$className => $probeFile]);
        // The probe lives outside of the woven paths, so it is included as is
        $loader = $this->createLoader($composerLoader, [$this->appDir . '/lib']);

        $this->assertTrue($loader->loadClass($className));
        $this->assertTrue(class_exists($className, false));
        $this->assertFalse(IsolatedScopeProbe::$sawThis, 'The loader must not leak $this into included files');
    }

    public function testLoadClassReturnsNullForUnknownClass(): void
    {
        $loader = $this->createLoader(new ClassLoader());

        $this->assertNull($loader->loadClass(__NAMESPACE__ . '\\UnknownClass'));
    }

    public function testDebugModeIncludesFreshUntransformedFileByItsOriginalPath(): void
    {
        $file   = $this->appDir . '/src/Service.php';
        $loader = $this->createDebugLoader($file, ['filemtime' => 1, 'filesize' => 0, 'cachedAt' => 1, 'cacheUri' => null]);

        // A plain path: opcache caches the file, it never caches a php://filter include
        $this->assertSame($file, $loader->findFile('App\\Service'));
    }

    public function testDebugModeStreamsStaleOrUnknownFileThroughTheFilter(): void
    {
        $file   = $this->appDir . '/src/Service.php';
        $loader = $this->createDebugLoader($file, null);

        $this->assertSame(
            SourceTransformingLoader::PHP_FILTER_READ . SourceTransformingLoader::FILTER_IDENTIFIER . '/resource=' . $file,
            $loader->findFile('App\\Service'),
            'A stale record or a miss must reach the filter to be woven again',
        );
    }

    public function testDebugModeStreamsWovenFileThroughTheFilter(): void
    {
        $file   = $this->appDir . '/src/Service.php';
        $loader = $this->createDebugLoader($file, ['cacheUri' => $this->appDir . '/cache/src/Service.php']);

        $this->assertSame(
            SourceTransformingLoader::PHP_FILTER_READ . SourceTransformingLoader::FILTER_IDENTIFIER . '/resource=' . $file,
            $loader->findFile('App\\Service'),
            'Magic constants and breakpoints of a woven file rely on the filter in debug mode',
        );
    }

    public function testDebugModeSendsClassOfTheWovenClassMapToTheFilterWithoutFreshnessCheck(): void
    {
        $file           = $this->appDir . '/src/Service.php';
        $composerLoader = new ClassLoader();
        $composerLoader->addClassMap(['App\\Service' => $file]);
        $cachePathManager = $this->createMock(CachePathManager::class);
        $cachePathManager->method('queryClassMap')->willReturn(['App\\Service' => $this->appDir . '/cache/src/Service.php']);
        $cachePathManager->method('querySkippedClasses')->willReturn([]);
        // The filter checks the record of a woven file anyway
        $cachePathManager->expects($this->never())->method('queryFreshCacheState');
        $this->configureFilterInjector($cachePathManager);

        $loader = new AopComposerLoader($composerLoader, $this->createContainer($cachePathManager), $this->createOptions([]));

        $this->assertSame(
            SourceTransformingLoader::PHP_FILTER_READ . SourceTransformingLoader::FILTER_IDENTIFIER . '/resource=' . $file,
            $loader->findFile('App\\Service'),
        );
    }

    public function testDebugModeKeepsExcludedFilesUntouchedWithoutQueryingTheCache(): void
    {
        $file           = $this->appDir . '/vendor/goaop/dissect/src/Parser.php';
        $composerLoader = new ClassLoader();
        $composerLoader->addClassMap(['Dissect\\Parser' => $file]);
        $cachePathManager = $this->createMock(CachePathManager::class);
        $cachePathManager->method('queryClassMap')->willReturn([]);
        $cachePathManager->method('querySkippedClasses')->willReturn([]);
        $cachePathManager->expects($this->never())->method('queryFreshCacheState');

        $loader = new AopComposerLoader($composerLoader, $this->createContainer($cachePathManager), $this->createOptions([]));

        $this->assertSame($file, $loader->findFile('Dissect\\Parser'));
    }

    public function testProductionModeNeverChecksFreshnessOfUnknownClasses(): void
    {
        $file           = $this->appDir . '/src/Service.php';
        $composerLoader = new ClassLoader();
        $composerLoader->addClassMap(['App\\Service' => $file]);
        $cachePathManager = $this->createMock(CachePathManager::class);
        $cachePathManager->method('queryClassMap')->willReturn([]);
        $cachePathManager->method('querySkippedClasses')->willReturn([]);
        $cachePathManager->expects($this->never())->method('queryFreshCacheState');
        $this->configureFilterInjector($cachePathManager);

        $loader = new AopComposerLoader(
            $composerLoader,
            $this->createContainer($cachePathManager),
            ['debug' => false] + $this->createOptions([]),
        );

        $this->assertStringStartsWith(SourceTransformingLoader::PHP_FILTER_READ, (string) $loader->findFile('App\\Service'));
    }

    public function testFindOriginalFileResolvesClassThroughWrappedComposerLoaderWithoutLoadingIt(): void
    {
        $className      = __NAMESPACE__ . '\\NeverLoadedProbe';
        $composerLoader = new ClassLoader();
        $composerLoader->addClassMap([$className => $this->appDir . '/src/Service.php']);
        $composerLoader->register(true);
        $registeredLoaders = spl_autoload_functions();
        try {
            $this->assertNull(AopComposerLoader::findOriginalFile($className), 'Unwrapped composer loaders are not consulted');

            AopComposerLoader::init($this->createOptions([]), $this->createContainer());

            $this->assertSame($this->appDir . '/src/Service.php', AopComposerLoader::findOriginalFile($className));
            $this->assertNull(AopComposerLoader::findOriginalFile(__NAMESPACE__ . '\\UnknownClass'));
            $this->assertFalse(class_exists($className, false));
        } finally {
            foreach (spl_autoload_functions() as $loader) {
                spl_autoload_unregister($loader);
            }
            foreach ($registeredLoaders as $loader) {
                spl_autoload_register($loader);
            }
            $composerLoader->unregister();
        }
    }

    public function testOriginalLoaderIsExposed(): void
    {
        $composerLoader = new ClassLoader();

        $this->assertSame($composerLoader, $this->createLoader($composerLoader)->getOriginalLoader());
    }

    public function testInitWrapsComposerLoaderAndRewrapsItOnRepeatedInit(): void
    {
        $composerLoader = new ClassLoader();
        $composerLoader->register(true);
        $registeredLoaders = spl_autoload_functions();
        try {
            $options = $this->createOptions([]);
            $this->assertTrue(AopComposerLoader::init($options, $this->createContainer()));
            $firstWrapper = $this->findWrapperOf($composerLoader);
            $this->assertNotNull($firstWrapper);

            // Booting again replaces the wrapper instead of wrapping the wrapper
            $this->assertTrue(AopComposerLoader::init($options, $this->createContainer()));
            $secondWrapper = $this->findWrapperOf($composerLoader);

            $this->assertNotSame($firstWrapper, $secondWrapper);
            $this->assertNull($this->findWrapperOf($firstWrapper));
        } finally {
            foreach (spl_autoload_functions() as $loader) {
                spl_autoload_unregister($loader);
            }
            foreach ($registeredLoaders as $loader) {
                spl_autoload_register($loader);
            }
            $composerLoader->unregister();
        }
    }

    /**
     * Returns the registered AopComposerLoader wrapping the given loader
     */
    private function findWrapperOf(object $originalLoader): ?AopComposerLoader
    {
        foreach (spl_autoload_functions() as $loader) {
            if (is_array($loader) && $loader[0] instanceof AopComposerLoader && $loader[0]->getOriginalLoader() === $originalLoader) {
                return $loader[0];
            }
        }

        return null;
    }

    /**
     * @param list<string> $excludePaths
     */
    private function createLoader(ClassLoader $composerLoader, array $excludePaths = []): AopComposerLoader
    {
        return new AopComposerLoader($composerLoader, $this->createContainer(), $this->createOptions($excludePaths));
    }

    /**
     * Debug options weaving nothing but the application sources
     *
     * @param list<string> $excludePaths
     *
     * @return array{debug: bool, appDir: string, cacheDir: string|null, cacheFileMode: int, features: int, includePaths: list<string>, excludePaths: list<string>, containerClass: class-string<AspectContainer>}
     */
    private function createOptions(array $excludePaths): array
    {
        return [
            'debug'          => true,
            'appDir'         => $this->appDir,
            'cacheDir'       => null,
            'cacheFileMode'  => 0770,
            'features'       => 0,
            'includePaths'   => [$this->appDir . '/src'],
            'excludePaths'   => $excludePaths,
            'containerClass' => Container::class,
        ];
    }

    /**
     * Debug loader resolving App\\Service to the given file, whose cache record queryFreshCacheState() reports
     *
     * @param array<string, mixed>|null $freshCacheState
     */
    private function createDebugLoader(string $file, ?array $freshCacheState): AopComposerLoader
    {
        $composerLoader = new ClassLoader();
        $composerLoader->addClassMap(['App\\Service' => $file]);

        $cachePathManager = $this->createStub(CachePathManager::class);
        $cachePathManager->method('queryClassMap')->willReturn([]);
        $cachePathManager->method('querySkippedClasses')->willReturn([]);
        $cachePathManager->method('queryFreshCacheState')->willReturn($freshCacheState);
        $this->configureFilterInjector($cachePathManager);

        return new AopComposerLoader($composerLoader, $this->createContainer($cachePathManager), $this->createOptions([]));
    }

    /**
     * Configures the php://filter rewriting without a booted kernel
     */
    private function configureFilterInjector(CachePathManager $cachePathManager): void
    {
        $kernel = $this->createStub(AspectKernel::class);
        $kernel->method('getOptions')->willReturn($this->createOptions([]));
        new FilterInjectorTransformer($kernel, SourceTransformingLoader::FILTER_IDENTIFIER, $cachePathManager);
    }

    private function createContainer(?CachePathManager $cachePathManager = null): AspectContainer
    {
        if ($cachePathManager === null) {
            $cachePathManager = $this->createStub(CachePathManager::class);
            $cachePathManager->method('queryClassMap')->willReturn([]);
            $cachePathManager->method('querySkippedClasses')->willReturn([]);
        }

        $container = $this->createStub(AspectContainer::class);
        $container->method('getService')->willReturn($cachePathManager);

        return $container;
    }
}
