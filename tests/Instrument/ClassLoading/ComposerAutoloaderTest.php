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
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The PHPUnit process itself runs on the Composer autoloader of this repository
 */
class ComposerAutoloaderTest extends TestCase
{
    public function testLocatesTheAutoloaderOfTheProcess(): void
    {
        $autoloader = ComposerAutoloader::ofProcess();

        $this->assertSame(realpath(__DIR__ . '/../../../vendor'), realpath($autoloader->vendorDir));
        $this->assertFileExists($autoloader->autoloadFile);
        $this->assertContains($autoloader->classLoader, ClassLoader::getRegisteredLoaders());
    }

    /**
     * The loader the kernel wrapped wins over the registered ones; a loader created by hand (no vendor directory
     * registered by Composer) is located through the installed ClassLoader class
     */
    public function testPrefersTheLoaderWrappedByTheKernel(): void
    {
        $classLoader = new ClassLoader();
        $aopLoader   = new AopComposerLoader($classLoader, $this->createContainer(), [
            'debug'          => true,
            'appDir'         => __DIR__,
            'cacheDir'       => null,
            'cacheFileMode'  => 0770,
            'features'       => 0,
            'includePaths'   => [],
            'excludePaths'   => [],
            'containerClass' => Container::class,
            'parsedFilesLimit' => 100,
        ]);

        // The array callable AopComposerLoader::init() registers, ahead of Composer's own
        $autoloader = ComposerAutoloader::fromLoaders([[$aopLoader, 'loadClass'], ...spl_autoload_functions()], ClassLoader::getRegisteredLoaders());

        $this->assertSame($classLoader, $autoloader->classLoader);
        $this->assertSame(realpath(__DIR__ . '/../../../vendor'), realpath($autoloader->vendorDir));
    }

    public function testFallsBackToTheFirstRegisteredLoader(): void
    {
        $classLoader = new ClassLoader('/app/vendor');

        $autoloader = ComposerAutoloader::fromLoaders([], ['/app/vendor' => $classLoader, '/other/vendor' => new ClassLoader()]);

        $this->assertSame($classLoader, $autoloader->classLoader);
        $this->assertSame('/app/vendor', $autoloader->vendorDir);
        $this->assertSame('/app/vendor/autoload.php', $autoloader->autoloadFile);
        $this->assertSame([], $autoloader->autoloadedFiles(), 'No autoload_files.php there');
    }

    public function testRequiresAComposerLoader(): void
    {
        try {
            ComposerAutoloader::fromLoaders([static fn(string $class): bool => false], []);
            $this->fail('Without a Composer loader there is no autoloader to describe');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('No Composer class loader is registered in this process', $exception->getMessage());
        }
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

    public function testListsTheFilesComposerIncludesOnEveryRequest(): void
    {
        $files = ComposerAutoloader::ofProcess()->autoloadedFiles();

        $this->assertNotEmpty($files, 'The dependencies of this repository have autoloaded files');
        foreach ($files as $file) {
            $this->assertFileExists($file);
        }
    }
}
