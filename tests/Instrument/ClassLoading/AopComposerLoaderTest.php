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
use Go\Instrument\FileSystem\Enumerator;
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

    private function createContainer(): AspectContainer
    {
        $cachePathManager = $this->createStub(CachePathManager::class);
        $cachePathManager->method('queryClassMap')->willReturn([]);
        $cachePathManager->method('querySkippedClasses')->willReturn([]);

        $container = $this->createStub(AspectContainer::class);
        $container->method('getService')->willReturn($cachePathManager);

        return $container;
    }
}
