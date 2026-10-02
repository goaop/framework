<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2025, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\ClassLoading;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\Container;
use Go\PhpUnit\UsesTemporaryDirectory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

// Separate processes: warming up registers the process-wide source transforming
// stream filter, which must not leak into the rest of the PHPUnit process
#[RunTestsInSeparateProcesses]
#[AllowMockObjectsWithoutExpectations]
class CacheWarmerTest extends TestCase
{
    use UsesTemporaryDirectory;

    private string $appDir;
    private string $cacheDir;
    private string $sourceFile;

    protected function setUp(): void
    {
        // Real directories: warming up includes woven sources through the stream filter
        $this->appDir   = self::createTemporaryDirectory('warmer-app');
        $this->cacheDir = self::createTemporaryDirectory('warmer-cache');
        mkdir($this->appDir . '/src');

        $this->sourceFile = $this->appDir . '/src/Some.php';
        file_put_contents($this->sourceFile, "<?php echo 'original';\n");
    }

    protected function tearDown(): void
    {
        self::removeTemporaryDirectory($this->cacheDir);
        self::removeTemporaryDirectory($this->appDir);
    }

    /**
     * Creates a kernel mock wired to a container mock, mirroring what the cache
     * warmer receives from a real aspect kernel
     *
     * @return AspectKernel&MockObject
     */
    private function createKernel(?string $cacheDir): AspectKernel
    {
        $kernel = $this->createMock(AspectKernel::class);
        $kernel->method('getOptions')->willReturn([
            'debug'          => true,
            'appDir'         => $this->appDir,
            'cacheDir'       => $cacheDir,
            'cacheFileMode'  => 0770,
            'features'       => 0,
            'includePaths'   => [],
            'excludePaths'   => [],
            'containerClass' => Container::class,
        ]);

        $container = $this->createMock(AspectContainer::class);
        $container->method('getService')->willReturnMap([
            [AspectKernel::class, $kernel],
            [CachePathManager::class, new CachePathManager($kernel)],
        ]);
        // No transformers: streamed files pass through the filter untransformed
        $container->method('getServicesByInterface')->willReturn([]);
        $kernel->method('getContainer')->willReturn($container);

        return $kernel;
    }

    public function testRequiresConfiguredCacheDir(): void
    {
        $warmer = new CacheWarmer($this->createKernel(null));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cacheDir');

        $warmer->warmUp();
    }

    public function testWarmUpProcessesAllEnumeratedFiles(): void
    {
        $output = new BufferedOutput();
        $warmer = new CacheWarmer($this->createKernel($this->cacheDir), $output);

        $warmer->warmUp();
        $display = $output->fetch();

        $this->assertStringContainsString('Total 1 files to process.', $display);
        $this->assertStringContainsString('[OK]', $display);
        $this->assertStringContainsString('[DONE]: Total processed 1, 0 errors.', $display);
    }

    public function testInterruptStopsWarmupLoopBeforeNextFile(): void
    {
        $output = new BufferedOutput();
        $warmer = new CacheWarmer($this->createKernel($this->cacheDir), $output);

        $warmer->interrupt();
        $warmer->warmUp();
        $display = $output->fetch();

        $this->assertStringContainsString('[STOP]: Warmup was interrupted, stopping...', $display);
        $this->assertStringNotContainsString('[OK]', $display);
        $this->assertStringContainsString('[DONE]: Total processed 0, 0 errors.', $display);
    }
}
