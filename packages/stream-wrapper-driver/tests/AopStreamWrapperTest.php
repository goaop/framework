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

namespace Go\Driver\StreamWrapper;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\Container;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\Instrument\ClassLoading\SourceTransformingLoader;
use Go\Instrument\Transformer\SourceTransformer;
use Go\Instrument\Transformer\StreamMetaData;
use Go\Instrument\Transformer\TransformerResult;
use Go\PhpUnit\UsesTemporaryDirectory;
use PhpToken;
use PHPUnit\Framework\TestCase;

/**
 * The replaced `file://` wrapper: plain file operations keep working natively, an include from within the weaving
 * scope is served transformed under its original path
 */
final class AopStreamWrapperTest extends TestCase
{
    use UsesTemporaryDirectory;

    private const int STREAM_OPEN_FOR_INCLUDE = 0x80;

    private string $appDir;

    private string $cacheDir;

    private string $includePath;

    private MarkerRewritingTransformer $transformer;

    private ?CachePathManager $cachePathManager = null;

    protected function setUp(): void
    {
        // Real directories: the wrapper resolves the included path and includes files, the cache is written to disk
        $this->appDir   = self::createTemporaryDirectory('stream-wrapper-app');
        $this->cacheDir = self::createTemporaryDirectory('stream-wrapper-cache');
        mkdir($this->appDir . '/src');
        mkdir($this->appDir . '/lib');
        $this->includePath = get_include_path();
        $this->transformer = new MarkerRewritingTransformer();
        SourceTransformingLoader::reset();
    }

    protected function tearDown(): void
    {
        try {
            AopStreamWrapper::unregister();
            SourceTransformingLoader::reset();
            set_include_path($this->includePath);
            $this->cachePathManager?->clearCacheState();
        } finally {
            self::removeTemporaryDirectory($this->cacheDir);
            self::removeTemporaryDirectory($this->appDir);
        }
    }

    public function testPlainFileFunctionsAreDelegatedToTheNativeWrapper(): void
    {
        $this->registerWrapper();
        $file = $this->appDir . '/lib/plain.txt';

        $this->assertSame(5, file_put_contents($file, 'hello'));
        $this->assertSame('hello', file_get_contents($file));
        $this->assertTrue(is_file($file));
        $this->assertFalse(is_dir($file));
        $this->assertSame(5, filesize($file));
        $this->assertSame(['hello'], file($file));
        $this->assertFalse(file_exists($this->appDir . '/lib/missing.txt'));
        $this->assertIsInt(filemtime($file));

        $this->assertTrue(rename($file, $this->appDir . '/lib/renamed.txt'));
        $this->assertFalse(file_exists($file));
        $this->assertTrue(copy($this->appDir . '/lib/renamed.txt', $this->appDir . '/lib/copied.txt'));
        $this->assertSame('hello', file_get_contents($this->appDir . '/lib/copied.txt'));
        $this->assertTrue(unlink($this->appDir . '/lib/renamed.txt'));
        $this->assertTrue(unlink($this->appDir . '/lib/copied.txt'));
        $this->assertFalse(file_exists($this->appDir . '/lib/renamed.txt'));
    }

    public function testStreamOperationsAreDelegatedToTheNativeHandle(): void
    {
        $this->registerWrapper();
        $file = $this->appDir . '/lib/stream.txt';
        file_put_contents($file, '0123456789', LOCK_EX);

        $handle = fopen($file, 'r+');
        $this->assertIsResource($handle);
        $this->assertSame('0123', fread($handle, 4));
        $this->assertSame(4, ftell($handle));
        $this->assertSame(0, fseek($handle, 8));
        $this->assertSame('89', fread($handle, 4));
        $this->assertSame('', fread($handle, 1));
        $this->assertTrue(feof($handle));
        $stat = fstat($handle);
        $this->assertIsArray($stat);
        $this->assertSame(10, $stat['size']);
        $this->assertTrue(flock($handle, LOCK_EX));
        $this->assertTrue(flock($handle, LOCK_UN));
        $this->assertTrue(ftruncate($handle, 3));
        $this->assertTrue(rewind($handle));
        $this->assertSame(2, fwrite($handle, 'ab'));
        $this->assertTrue(fflush($handle));
        $this->assertTrue(fclose($handle));
        $this->assertSame('ab2', file_get_contents($file));

        $this->assertSame(1, file_put_contents($file, 'x', FILE_APPEND | LOCK_EX));
        $this->assertSame('ab2x', file_get_contents($file));
    }

    public function testDirectoryOperationsAreDelegatedToTheNativeWrapper(): void
    {
        $this->registerWrapper();
        $nested = $this->appDir . '/lib/a/b/c';

        $this->assertTrue(mkdir($nested, 0777, true));
        $this->assertTrue(is_dir($nested));
        touch($nested . '/one.txt');
        touch($nested . '/two.txt');

        $directory = opendir($nested);
        $this->assertIsResource($directory);
        $entries = [];
        while (($entry = readdir($directory)) !== false) {
            $entries[] = $entry;
        }
        rewinddir($directory);
        $this->assertIsString(readdir($directory), 'The directory can be read again after rewinding');
        closedir($directory);
        sort($entries);
        $this->assertSame(['.', '..', 'one.txt', 'two.txt'], $entries);
        $this->assertSame(['.', '..', 'one.txt', 'two.txt'], scandir($nested));

        unlink($nested . '/one.txt');
        unlink($nested . '/two.txt');
        $this->assertTrue(rmdir($nested));
        $this->assertFalse(is_dir($nested));
        $this->assertFalse(@rmdir($this->appDir . '/lib/a'), 'A directory that is not empty is refused natively');
        $this->assertTrue(rmdir($this->appDir . '/lib/a/b'));
        $this->assertTrue(rmdir($this->appDir . '/lib/a'));
    }

    public function testMetadataOperationsAreDelegatedToTheNativeWrapper(): void
    {
        $this->registerWrapper();
        $file = $this->appDir . '/lib/meta.txt';

        $this->assertTrue(touch($file, 1_000_000_000));
        $this->assertTrue(file_exists($file));
        clearstatcache();
        $this->assertSame(1_000_000_000, filemtime($file));
        $this->assertTrue(chmod($file, 0600));
        clearstatcache();
        if (DIRECTORY_SEPARATOR === '/') {
            // Windows keeps only the read-only bit of a mode
            $this->assertSame(0600, fileperms($file) & 0777);
        }
    }

    public function testAMissingFileFailsNatively(): void
    {
        $this->registerWrapper();
        $missing = $this->appDir . '/lib/missing.php';

        $this->assertFalse(@fopen($missing, 'r'));
        $this->assertFalse(@file_get_contents($missing));
        $this->assertFalse(file_exists($missing));
        $this->assertFalse(@include $missing);
    }

    public function testIncludeOutsideTheWeavingScopeIsServedUntouched(): void
    {
        $this->registerWrapper();
        $file = $this->appDir . '/lib/outside.php';
        file_put_contents($file, "<?php return ['value' => 'MARKER', 'file' => __FILE__];");

        // __FILE__ is the path as the platform spells it (backslashes on Windows)
        $this->assertSame(['value' => 'MARKER', 'file' => realpath($file)], include $file);
        $this->assertSame(0, $this->transformer->callCount, 'A file outside the scope never reaches the chain');
        $this->assertFileDoesNotExist($this->cacheDir . '/lib/outside.php');
    }

    public function testIncludeWithinTheWeavingScopeIsTransformedAndCached(): void
    {
        $this->registerWrapper();
        $file = $this->appDir . '/src/inside.php';
        file_put_contents($file, "<?php return ['value' => 'MARKER', 'file' => __FILE__];");

        // Served from memory under the original path: the magic constants resolve to the original file
        $this->assertSame(['value' => 'REWRITTEN', 'file' => realpath($file)], include $file);
        $this->assertSame(1, $this->transformer->callCount);
        $cachedFile = $this->cacheDir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'inside.php';
        $this->assertFileExists($cachedFile);
        $this->assertStringContainsString('REWRITTEN', (string) file_get_contents($cachedFile));
        // Recorded under the resolved path (backslashes on Windows)
        $this->assertNotNull($this->cachePathManager?->queryCacheState((string) realpath($file)), 'The transformation is recorded');

        // A fresh cache record serves the cached content without running the chain again
        $this->assertSame(['value' => 'REWRITTEN', 'file' => $file], include $file);
        $this->assertSame(1, $this->transformer->callCount);
    }

    public function testIncludeStreamReportsTheServedLengthAndTheOriginalMtime(): void
    {
        $this->registerWrapper();
        $file = $this->appDir . '/src/served.php';
        file_put_contents($file, "<?php return 'MARKER';");
        touch($file, 1_000_000_000);
        clearstatcache();

        $wrapper    = new AopStreamWrapper();
        $openedPath = null;
        $this->assertTrue($wrapper->stream_open($file, 'rb', self::STREAM_OPEN_FOR_INCLUDE, $openedPath));
        $served = "<?php return 'REWRITTEN';";
        $stat   = $wrapper->stream_stat();
        $this->assertIsArray($stat);
        // PHP reads exactly st_size bytes of an included stream, opcache validates the file by its mtime
        $this->assertSame(strlen($served), $stat['size']);
        $this->assertSame(strlen($served), $stat[7]);
        $this->assertSame(1_000_000_000, $stat['mtime']);
        $this->assertSame($served, $wrapper->stream_read(1024));
        $this->assertTrue($wrapper->stream_eof());
        $this->assertFalse($wrapper->stream_cast(STREAM_CAST_AS_STREAM), 'A memory-served include has no native handle');
        $this->assertTrue($wrapper->stream_lock(LOCK_UN), 'A memory-served include has nothing to lock');
        $wrapper->stream_close();
    }

    public function testIncludeStreamOfAnUntransformedFileKeepsTheNativeHandle(): void
    {
        $this->registerWrapper();
        $file = $this->appDir . '/src/plain.php';
        file_put_contents($file, "<?php return 'nothing to rewrite';");

        $wrapper    = new AopStreamWrapper();
        $openedPath = null;
        $this->assertTrue($wrapper->stream_open($file, 'rb', self::STREAM_OPEN_FOR_INCLUDE, $openedPath));
        $stat = $wrapper->stream_stat();
        $this->assertIsArray($stat);
        $this->assertSame(filesize($file), $stat['size']);
        $this->assertSame("<?php return 'nothing to rewrite';", $wrapper->stream_read(1024));
        $this->assertIsResource($wrapper->stream_cast(STREAM_CAST_AS_STREAM));
        $wrapper->stream_close();
        $this->assertSame(1, $this->transformer->callCount, 'The chain ran and abstained');
    }

    public function testTheIncludePathIsSearchedForStreamUsePath(): void
    {
        $this->registerWrapper();
        $file = $this->appDir . '/lib/on-include-path.php';
        file_put_contents($file, "<?php return 'found';");
        set_include_path($this->appDir . '/lib');

        $wrapper    = new AopStreamWrapper();
        $openedPath = null;
        $this->assertTrue($wrapper->stream_open('on-include-path.php', 'r', STREAM_USE_PATH, $openedPath));
        $this->assertNotNull($openedPath);
        $this->assertSame(realpath($file), realpath($openedPath));
        $wrapper->stream_close();
        $this->assertSame("<?php return 'found';", file_get_contents('on-include-path.php', true));
    }

    public function testUnregisterRestoresTheNativeWrapperAndASecondRoundWorks(): void
    {
        $file = $this->appDir . '/src/round.php';
        file_put_contents($file, "<?php return 'MARKER';");

        $this->registerWrapper();
        $this->assertTrue(AopStreamWrapper::isRegistered());
        $this->assertSame('REWRITTEN', include $file);

        AopStreamWrapper::unregister();
        $this->assertFalse(AopStreamWrapper::isRegistered());
        $this->assertContains('file', stream_get_wrappers(), 'The native wrapper is back');
        $this->assertSame('MARKER', include $file);

        $this->registerWrapper();
        $this->assertTrue(AopStreamWrapper::isRegistered());
        $this->assertSame('REWRITTEN', include $file);
        AopStreamWrapper::unregister();
        $this->assertSame('MARKER', include $file);
    }

    public function testRegisterIsIdempotent(): void
    {
        $this->registerWrapper();
        $this->registerWrapper();
        $this->assertTrue(AopStreamWrapper::isRegistered());
        $this->assertContains('file', stream_get_wrappers());

        AopStreamWrapper::unregister();
        $this->assertFalse(AopStreamWrapper::isRegistered());
        AopStreamWrapper::unregister();
        $this->assertContains('file', stream_get_wrappers(), 'A second unregister() is harmless');
    }

    public function testOwnershipMetadataAndSymlinkStatsAreDelegatedToTheNativeWrapper(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped('chown(), chgrp() and unprivileged symlinks are not available on Windows');
        }
        $this->registerWrapper();
        $file = $this->appDir . '/lib/owned.txt';
        file_put_contents($file, 'mine');

        // chown/chgrp to the current owner and group are allowed without privileges
        $this->assertTrue(chown($file, (int) getmyuid()));
        $this->assertTrue(chgrp($file, (int) getmygid()));
        $this->assertSame((int) getmyuid(), fileowner($file));

        $link = $this->appDir . '/lib/owned.link';
        $this->assertTrue(symlink($file, $link));
        $this->assertTrue(is_link($link), 'lstat() goes through url_stat() with STREAM_URL_STAT_LINK');
        $this->assertSame('mine', file_get_contents($link));
        $linkStat = lstat($link);
        $this->assertIsArray($linkStat);
        $this->assertNotSame(filesize($file), $linkStat['size'], 'The stat of the link itself, not of its target');
        $this->assertTrue(unlink($link));
    }

    public function testAnUnopenedWrapperInstanceAnswersEveryStreamAndDirectoryOperationWithFalse(): void
    {
        $this->registerWrapper();
        $wrapper = new AopStreamWrapper();

        $this->assertFalse($wrapper->stream_read(8));
        $this->assertFalse($wrapper->stream_write('x'));
        $this->assertTrue($wrapper->stream_eof());
        $this->assertFalse($wrapper->stream_flush());
        $this->assertFalse($wrapper->stream_seek(0));
        $this->assertFalse($wrapper->stream_tell());
        $this->assertFalse($wrapper->stream_stat());
        $this->assertFalse($wrapper->stream_truncate(0));
        $this->assertFalse($wrapper->stream_lock(LOCK_SH));
        $this->assertFalse($wrapper->stream_cast(STREAM_CAST_FOR_SELECT));
        $this->assertFalse($wrapper->dir_readdir());
        $this->assertFalse($wrapper->dir_rewinddir());
        $this->assertTrue($wrapper->dir_closedir());
        $this->assertFalse($wrapper->stream_metadata($this->appDir . '/lib/none.txt', 999, null), 'An unknown metadata option');
        $this->assertFalse($wrapper->dir_opendir($this->appDir . '/lib/none', 0));
        $wrapper->stream_close();
    }

    public function testAnIncludeStreamOfADeletedFileHasNoStatAnyMore(): void
    {
        $this->registerWrapper();
        $file = $this->appDir . '/src/Vanishing.php';
        file_put_contents($file, "<?php\nreturn 'MARKER';\n");
        $wrapper = new AopStreamWrapper();
        $openedPath = null;

        $this->assertTrue($wrapper->stream_open($file, 'rb', self::STREAM_OPEN_FOR_INCLUDE, $openedPath));
        $this->assertStringContainsString('REWRITTEN', (string) $wrapper->stream_read(1024), 'Served from memory');
        $this->assertTrue(unlink($file));

        $this->assertFalse($wrapper->stream_stat(), 'The original file is gone, so is its stat');
        $wrapper->stream_close();
    }

    public function testAnIncludeThroughAnUnregisteredWrapperIsServedUntouched(): void
    {
        $file = $this->appDir . '/src/Unwired.php';
        file_put_contents($file, "<?php\nreturn 'MARKER';\n");
        $this->assertFalse(AopStreamWrapper::isRegistered());
        $wrapper = new AopStreamWrapper();
        $openedPath = null;

        // No container, no scope: the instance behaves like the native wrapper
        $this->assertTrue($wrapper->stream_open($file, 'rb', self::STREAM_OPEN_FOR_INCLUDE, $openedPath));
        $this->assertSame("<?php\nreturn 'MARKER';\n", $wrapper->stream_read(1024));
        $wrapper->stream_close();
    }

    /**
     * Registers the wrapper for a container serving the marker-rewriting chain, with the application sources
     * (appDir/src) as the weaving scope
     */
    private function registerWrapper(): void
    {
        $options = [
            'debug'          => true,
            'appDir'         => $this->appDir,
            'cacheDir'       => $this->cacheDir,
            'cacheFileMode'  => 0770,
            'features'       => 0,
            'includePaths'   => [$this->appDir . '/src'],
            'excludePaths'   => [$this->cacheDir],
            'containerClass' => Container::class,
            'driver'         => StreamWrapperDriver::class,
        ];
        $kernel = $this->createStub(AspectKernel::class);
        $kernel->method('getOptions')->willReturn($options);
        $kernel->method('hasFeature')->willReturn(false);
        $this->cachePathManager = new CachePathManager($kernel);

        $container = $this->createStub(AspectContainer::class);
        $container->method('getService')->willReturnMap([
            [AspectKernel::class, $kernel],
            [CachePathManager::class, $this->cachePathManager],
        ]);
        $container->method('getServicesByInterface')->willReturn([$this->transformer]);
        // The tracked resources never change during a test: a cache record stays fresh
        $container->method('isFreshSince')->willReturn(true);

        AopStreamWrapper::register($container, $options);
    }
}

/**
 * Transformer stub rewriting the string MARKER into REWRITTEN and counting its invocations
 */
final class MarkerRewritingTransformer implements SourceTransformer
{
    public int $callCount = 0;

    public function transform(StreamMetaData $metadata): TransformerResult
    {
        $this->callCount++;
        if (!str_contains($metadata->originalSource, 'MARKER')) {
            return TransformerResult::Abstain;
        }
        $metadata->setTokenStreamFromRawTokens(
            ...PhpToken::tokenize(str_replace('MARKER', 'REWRITTEN', $metadata->originalSource)),
        );

        return TransformerResult::Transformed;
    }
}
