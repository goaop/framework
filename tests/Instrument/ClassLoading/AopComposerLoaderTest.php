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

    #[\Override]
    protected function setUp(): void
    {
        // Real directory: the file enumerator resolves candidate files with realpath()
        $this->appDir = self::createTemporaryDirectory('composer-loader');
        mkdir($this->appDir . '/vendor/goaop/dissect/src', 0777, true);
        mkdir($this->appDir . '/src', 0777, true);
        touch($this->appDir . '/vendor/goaop/dissect/src/Parser.php');
        touch($this->appDir . '/src/Service.php');
    }

    #[\Override]
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
