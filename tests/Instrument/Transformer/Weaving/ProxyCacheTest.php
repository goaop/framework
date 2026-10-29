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

namespace Go\Instrument\Transformer\Weaving;

use Go\Core\AspectContainer;
use Go\Core\Cache\CacheFileWriter;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\ParserReflection\ReflectionClass;
use Go\VirtualFileSystem\FileSystem;
use PhpParser\Node\Stmt\Class_;
use PHPUnit\Framework\TestCase;

final class ProxyCacheTest extends TestCase
{
    private FileSystem $fileSystem;

    private string $cacheDir;

    protected function setUp(): void
    {
        $this->fileSystem = FileSystem::mount('proxycachetest');
        $this->cacheDir   = $this->fileSystem->path('/cache');
    }

    protected function tearDown(): void
    {
        $this->fileSystem->unmount();
    }

    public function testOnlyClassProxyIsNamedLikeSourceFile(): void
    {
        $include = $this->createProxyCache()->saveClassProxy(self::createClass('App\First', '/app/src/Holder.php'), 'class First {}', true);

        $this->assertSame("include_once AOP_CACHE_DIR . '/src/Holder.php';", $include);
        $this->assertSame("<?php" . PHP_EOL . "class First {}", file_get_contents($this->cacheDir . '/src/Holder.php'));
    }

    public function testSwitchingLayoutsRemovesProxiesOfOtherLayout(): void
    {
        $proxyCache = $this->createProxyCache();
        $proxyCache->saveClassProxy(self::createClass('App\First', '/app/src/Holder.php'), 'class First {}', true);

        $include = $proxyCache->saveClassProxy(self::createClass('App\First', '/app/src/Holder.php'), 'class First {}', false);
        $proxyCache->saveClassProxy(self::createClass('App\Second', '/app/src/Holder.php'), 'class Second {}', false);

        $this->assertSame("include_once AOP_CACHE_DIR . '/src/Holder.php/App/First.php';", $include);
        $this->assertFileExists($this->cacheDir . '/src/Holder.php/App/Second.php');

        $proxyCache->saveClassProxy(self::createClass('App\First', '/app/src/Holder.php'), 'class First {}', true);

        $this->assertTrue(is_file($this->cacheDir . '/src/Holder.php'));
    }

    public function testClassOutsideApplicationKeepsAbsolutePath(): void
    {
        $include = $this->createProxyCache()->saveClassProxy(self::createClass('Lib\Foo', '/lib/Foo.php'), 'class Foo {}', true);

        $this->assertSame("include_once AOP_CACHE_DIR . '/lib/Foo.php';", $include);
    }

    public function testNothingIsSavedWithoutCacheOrSourceFile(): void
    {
        $this->assertSame('', $this->createProxyCache(null)->saveClassProxy(self::createClass('App\Foo', '/app/Foo.php'), '', true));
        $this->assertSame('', $this->createProxyCache()->saveClassProxy(self::createClass('App\Foo', null), '', true));
        $this->assertNull($this->createProxyCache(null)->saveFunctionProxy('App', static fn(): string => ''));
    }

    public function testFunctionProxyIsGeneratedOnlyWhenStale(): void
    {
        $generated = 0;
        $generate  = static function () use (&$generated): string {
            ++$generated;

            return '<?php // functions';
        };

        $include = $this->createProxyCache(isFresh: false)->saveFunctionProxy('App\Utils', $generate);
        $this->createProxyCache(isFresh: true)->saveFunctionProxy('App\Utils', $generate);

        $this->assertSame("include_once AOP_CACHE_DIR . '/_functions/App/Utils.php';", $include);
        $this->assertSame('<?php // functions', file_get_contents($this->cacheDir . '/_functions/App/Utils.php'));
        $this->assertSame(1, $generated);
    }

    private function createProxyCache(?string $cacheDir = '', bool $isFresh = true): ProxyCache
    {
        $cachePathManager = $this->createStub(CachePathManager::class);
        $cachePathManager->method('getCacheDir')->willReturn($cacheDir === '' ? $this->cacheDir : $cacheDir);
        $cachePathManager->method('getCacheFileWriter')->willReturn(new CacheFileWriter(0644));
        $container = $this->createStub(AspectContainer::class);
        $container->method('isFreshSince')->willReturn($isFresh);

        return new ProxyCache($cachePathManager, $container, '/app');
    }

    private static function createClass(string $className, ?string $fileName): ReflectionClass
    {
        $classNode = new Class_(substr((string) strrchr('\\' . $className, '\\'), 1));
        if ($fileName !== null) {
            $classNode->setAttribute('fileName', $fileName);
        }

        return new ReflectionClass($className, $classNode);
    }
}
