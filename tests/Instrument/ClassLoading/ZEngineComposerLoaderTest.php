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
use Go\Aop\WeavingDriver;
use Go\Core\AspectContainer;
use Go\Core\Container;
use Go\PhpUnit\UsesTemporaryDirectory;
use PHPUnit\Framework\TestCase;

/**
 * The composer loader wrapper of the z-engine driver: native loading, weaving only within the woven paths
 */
final class ZEngineComposerLoaderTest extends TestCase
{
    use UsesTemporaryDirectory;

    private string $appDir;

    /**
     * Autoloaders of the test runner, restored in tearDown() (init() wraps composer's real loader too)
     *
     * @var list<callable>
     */
    private array $originalLoaders = [];

    protected function setUp(): void
    {
        $this->originalLoaders = spl_autoload_functions();
        // Real directory: the file enumerator resolves candidate files with realpath()
        $this->appDir = self::createTemporaryDirectory('zengine-loader');
        mkdir($this->appDir . '/src');
        mkdir($this->appDir . '/lib');
    }

    protected function tearDown(): void
    {
        foreach (spl_autoload_functions() as $loader) {
            spl_autoload_unregister($loader);
        }
        foreach ($this->originalLoaders as $loader) {
            spl_autoload_register($loader);
        }
        self::removeTemporaryDirectory($this->appDir);
    }

    public function testInitWrapsEveryComposerLoaderAndUnwrapsEarlierWrappers(): void
    {
        $composerLoader = new ClassLoader();
        $otherLoader    = static function (string $class): void {};
        $this->register([$composerLoader, 'loadClass']);
        // A loader wrapped by an earlier init() of either driver is wrapped again around composer's loader
        $this->register([new ZEngineComposerLoader($composerLoader, $this->createContainer(), $this->createOptions()), 'loadClass']);
        $this->register($otherLoader);

        $this->assertTrue(ZEngineComposerLoader::init($this->createOptions(), $this->createContainer()));

        $wrappers = [];
        foreach (spl_autoload_functions() as $loader) {
            if (is_array($loader) && $loader[0] instanceof ZEngineComposerLoader && $loader[0]->getOriginalLoader() === $composerLoader) {
                $wrappers[] = $loader[0];
            }
            $this->assertFalse(is_array($loader) && $loader[0] === $composerLoader, 'The bare composer loader is replaced');
        }
        $this->assertCount(2, $wrappers, 'The bare loader and the earlier wrapper are both wrapped around the composer loader');
        $this->assertContains($otherLoader, spl_autoload_functions(), 'Other autoloaders stay registered');
    }

    public function testInitReportsWhenNoComposerLoaderIsRegistered(): void
    {
        // Composer's real loader is registered in the test runner: without it only a plain autoloader remains
        foreach (spl_autoload_functions() as $loader) {
            spl_autoload_unregister($loader);
        }
        $this->register(static function (string $class): void {});

        $this->assertFalse(ZEngineComposerLoader::init($this->createOptions(), $this->createContainer()));
    }

    public function testLoadsAClassOutsideTheWovenPathsNativelyWithoutWeaving(): void
    {
        $className = __NAMESPACE__ . '\\ZEngineLoaderProbe';
        $probeFile = $this->appDir . '/lib/ZEngineLoaderProbe.php';
        file_put_contents($probeFile, <<<'PHP'
            <?php
            namespace Go\Instrument\ClassLoading;

            final class ZEngineLoaderProbe
            {
                public static bool $sawThis;
            }
            ZEngineLoaderProbe::$sawThis = isset($this);
            PHP);
        $composerLoader = new ClassLoader();
        $composerLoader->addClassMap([$className => $probeFile]);
        $container = $this->createMock(AspectContainer::class);
        $container->expects($this->never())->method('getService');

        $loader = new ZEngineComposerLoader($composerLoader, $container, $this->createOptions());

        $this->assertTrue($loader->loadClass($className));
        $this->assertTrue(class_exists($className, false));
        $this->assertFalse(ZEngineLoaderProbe::$sawThis, 'The file is included in an isolated scope');
        $this->assertNull($loader->loadClass('Totally\\Unknown\\ZEngineClass'));
        $this->assertSame($probeFile, $loader->findFile($className));
        $this->assertFalse($loader->findFile('Totally\\Unknown\\ZEngineClass'));
    }

    public function testTheOriginalFileOfAClassIsFoundThroughTheWrapper(): void
    {
        $composerLoader = new ClassLoader();
        $composerLoader->addClassMap(['App\\Known' => $this->appDir . '/src/Known.php']);
        $this->register([new ZEngineComposerLoader($composerLoader, $this->createContainer(), $this->createOptions()), 'loadClass']);

        $this->assertSame($this->appDir . '/src/Known.php', AopComposerLoader::findOriginalFile('App\\Known'));
        $this->assertNull(AopComposerLoader::findOriginalFile('App\\Unknown'));
    }

    private function register(callable $loader): void
    {
        spl_autoload_register($loader);
    }

    /**
     * Options weaving the application sources only
     *
     * @return array{debug: bool, appDir: string, cacheDir: string|null, cacheFileMode: int, features: int, includePaths: list<string>, excludePaths: list<string>, containerClass: class-string<AspectContainer>, driver: WeavingDriver}
     */
    private function createOptions(): array
    {
        return [
            'debug'          => true,
            'appDir'         => $this->appDir,
            'cacheDir'       => null,
            'cacheFileMode'  => 0770,
            'features'       => 0,
            'includePaths'   => [$this->appDir . '/src'],
            'excludePaths'   => [],
            'containerClass' => Container::class,
            'driver'         => WeavingDriver::ZEngine,
        ];
    }

    private function createContainer(): AspectContainer
    {
        return $this->createStub(AspectContainer::class);
    }
}
