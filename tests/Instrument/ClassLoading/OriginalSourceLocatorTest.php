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
use Go\PhpUnit\UsesTemporaryDirectory;
use PHPUnit\Framework\TestCase;

class OriginalSourceLocatorTest extends TestCase
{
    use UsesTemporaryDirectory;

    private string $appDir;

    protected function setUp(): void
    {
        // Real directory: the located file is resolved with realpath()
        $this->appDir = self::createTemporaryDirectory('source-locator');
        mkdir($this->appDir . '/src');
        mkdir($this->appDir . '/cache');
        touch($this->appDir . '/src/Service.php');
        touch($this->appDir . '/cache/ServiceOriginalTrait.php');
    }

    protected function tearDown(): void
    {
        self::removeTemporaryDirectory($this->appDir);
    }

    public function testLocatesClassesThroughComposerLoadersThatAreNotWrapped(): void
    {
        $this->assertSame(__FILE__, new OriginalSourceLocator()->locateClass('\\' . self::class));
        $this->assertFalse(new OriginalSourceLocator()->locateClass(__NAMESPACE__ . '\\UnknownClass'));
    }

    public function testSkipsAutoloadersThatAreNoComposerLoaders(): void
    {
        $closureLoader = static function (string $className): void {};
        // An object loader that is no Composer loader, never called: the class below is declared already
        $objectLoader  = [new class {
            public function load(string $className): void {}
        }, 'load'];
        spl_autoload_register($closureLoader, true, true);
        spl_autoload_register($objectLoader, true, true);
        try {
            $this->assertSame(__FILE__, new OriginalSourceLocator()->locateClass(self::class), 'Composer is asked after them');
        } finally {
            spl_autoload_unregister($closureLoader);
            spl_autoload_unregister($objectLoader);
        }
    }

    public function testLocatesTheSourceOfAWovenClassInsteadOfItsCachedFile(): void
    {
        $composerLoader = new ClassLoader();
        $composerLoader->addPsr4('App\\', $this->appDir . '/src');
        $cachePathManager = $this->createStub(CachePathManager::class);
        $cachePathManager->method('queryClassMap')->willReturn(['App\\Service' => $this->appDir . '/cache/ServiceOriginalTrait.php']);
        $cachePathManager->method('querySkippedClasses')->willReturn([]);
        $container = $this->createStub(AspectContainer::class);
        $container->method('getService')->willReturn($cachePathManager);

        $loader = new AopComposerLoader($composerLoader, $container, [
            'debug'          => false,
            'appDir'         => $this->appDir,
            'cacheDir'       => null,
            'cacheFileMode'  => 0770,
            'features'       => 0,
            'includePaths'   => [],
            'excludePaths'   => [],
            'containerClass' => Container::class,
            'parsedFilesLimit' => 100,
        ]);
        $this->assertSame($this->appDir . '/cache/ServiceOriginalTrait.php', $composerLoader->findFile('App\\Service'));

        // Registered first, the way AopComposerLoader::init() leaves the wrapper of a composer loader
        // An array callable, as composer registers itself: loadClass() returns true when it loaded the class
        /** @var callable(string): void $autoloader */
        $autoloader = [$loader, 'loadClass'];
        spl_autoload_register($autoloader, true, true);
        try {
            // Resolved like realpath() does, with native separators
            $this->assertSame(realpath($this->appDir . '/src/Service.php'), new OriginalSourceLocator()->locateClass('App\\Service'));
        } finally {
            spl_autoload_unregister($autoloader);
        }
    }
}
