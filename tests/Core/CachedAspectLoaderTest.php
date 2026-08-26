<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Core;

use Go\Aop\Aspect;
use Go\Aop\Features;
use Go\Aop\Pointcut\TruePointcut;
use Go\Tests\TestProject\Aspect\DoSomethingAspect;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class CachedAspectLoaderTest extends TestCase
{
    private string $cacheDir;

    private AspectLoader $innerLoader;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/goaop-cached-loader-' . uniqid();
        mkdir($this->cacheDir . '/_aspect', 0777, true);
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->cacheDir . '/_aspect/*') ?: []);
        @rmdir($this->cacheDir . '/_aspect');
        @rmdir($this->cacheDir);
    }

    private function createLoader(int $features): CachedAspectLoader
    {
        $this->innerLoader = $this->createMock(AspectLoader::class);
        $container         = $this->createMock(AspectContainer::class);
        $container->method('getService')->willReturn($this->innerLoader);

        return new CachedAspectLoader($container, AspectLoader::class, [
            'debug'          => false,
            'appDir'         => '',
            'cacheDir'       => $this->cacheDir,
            'cacheFileMode'  => 0770,
            'features'       => $features,
            'includePaths'   => [],
            'excludePaths'   => [],
            'containerClass' => Container::class,
        ]);
    }

    private function writeStaleCacheFileFor(Aspect $aspect, string $content): string
    {
        $reflection = new ReflectionClass($aspect);
        $fileName   = $this->cacheDir . '/_aspect/' . sha1($reflection->getName());
        file_put_contents($fileName, $content);
        // Older than the aspect class file => stale by the freshness rules
        $aspectFileName = $reflection->getFileName();
        $this->assertIsString($aspectFileName);
        touch($fileName, (int) filemtime($aspectFileName) - 100);

        return $fileName;
    }

    public function testPrebuiltCacheTrustsExistingCacheFileWithoutFreshnessChecks(): void
    {
        $loader = $this->createLoader(Features::PREBUILT_CACHE);
        $aspect = new DoSomethingAspect();
        // The cache file is deliberately STALE - the prebuilt mode must trust it anyway
        $this->writeStaleCacheFileFor($aspect, serialize(['pointcut.true' => new TruePointcut()]));
        $this->innerLoader->expects($this->never())->method('load');

        $loadedItems = $loader->load($aspect);

        $this->assertArrayHasKey('pointcut.true', $loadedItems);
        $this->assertInstanceOf(TruePointcut::class, $loadedItems['pointcut.true']);
    }

    public function testWithoutPrebuiltCacheFeatureFreshnessRulesApply(): void
    {
        $loader = $this->createLoader(0);
        $aspect = new DoSomethingAspect();
        $this->writeStaleCacheFileFor($aspect, serialize(['pointcut.true' => new TruePointcut()]));
        // The stale cache file must be ignored and rebuilt through the direct loader
        $this->innerLoader->expects($this->once())->method('load')->willReturn([]);

        $this->assertSame([], $loader->load($aspect));
    }

    public function testPrebuiltCacheFallsBackToLoaderOnCorruptFileWithoutRewriting(): void
    {
        $loader = $this->createLoader(Features::PREBUILT_CACHE);
        $aspect = new DoSomethingAspect();
        $fileName = $this->writeStaleCacheFileFor($aspect, 'corrupt-data');
        $this->innerLoader->expects($this->once())->method('load')->willReturn([]);

        $this->assertSame([], $loader->load($aspect));
        // The file system may be read-only under PREBUILT_CACHE - nothing may be written
        $this->assertSame('corrupt-data', file_get_contents($fileName));
    }
}
