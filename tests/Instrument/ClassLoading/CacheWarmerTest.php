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
use Go\Instrument\Transformer\SourceTransformer;
use Go\Instrument\Transformer\StreamMetaData;
use Go\Instrument\Transformer\TransformerResult;
use Go\PhpUnit\UsesTemporaryDirectory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\StreamOutput;
use Go\Aop\WeavingDriver;

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
     * @param list<SourceTransformer> $transformers
     * @param list<string>            $includePaths
     * @param list<string>            $excludePaths
     *
     * @return AspectKernel&MockObject
     */
    private function createKernel(
        ?string $cacheDir,
        array $transformers = [],
        array $includePaths = [],
        array $excludePaths = [],
    ): AspectKernel {
        $kernel = $this->createMock(AspectKernel::class);
        $kernel->method('getOptions')->willReturn([
            'debug'          => true,
            'appDir'         => $this->appDir,
            'cacheDir'       => $cacheDir,
            'cacheFileMode'  => 0770,
            'features'       => 0,
            'includePaths'   => $includePaths,
            'excludePaths'   => $excludePaths,
            'containerClass' => Container::class,
            'driver'         => WeavingDriver::Stream,
        ]);

        $container = $this->createMock(AspectContainer::class);
        $container->method('getService')->willReturnMap([
            [AspectKernel::class, $kernel],
            [CachePathManager::class, new CachePathManager($kernel)],
        ]);
        // Without transformers, streamed files pass through the filter untransformed
        $container->method('getServicesByInterface')->willReturn($transformers);
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

    /**
     * The kernel excludes its cache directory, here below the application directory, like any other exclude path.
     * Several include paths: their files were once counted and then rewound, which a multi-directory Finder can not do
     */
    public function testWarmUpProcessesExactlyTheFilesOfTheEnumerator(): void
    {
        $cacheDir = $this->appDir . '/var/cache';
        $files    = [
            'src/Legacy/Old.php',
            'src/Generated/Proxy/ServiceProxy.php',
            'src/ServiceTest.php',
            'var/cache/src/Some.php',
            'vendor/acme/lib/src/Acme.php',
            'vendor/acme/lib/tests/AcmeTest.php',
            'vendor/autoload.php',
            'vendor-bin/Tool.php',
        ];
        foreach ($files as $file) {
            $path = $this->appDir . '/' . $file;
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }
            file_put_contents($path, "<?php\n");
        }
        $includePaths = [$this->appDir . '/src', $this->appDir . '/vendor', $this->appDir . '/var'];
        $excludePaths = [$cacheDir, $this->appDir . '/src/Legacy', $this->appDir . '/src/*/Proxy', $this->appDir . '/vendor/*/tests', $this->appDir . '/*Test.php'];

        $output = new BufferedOutput();
        $warmer = new CacheWarmer($this->createKernel($cacheDir, [], $includePaths, $excludePaths), $output);

        $this->assertSame(0, $warmer->warmUp());
        $display = $output->fetch();

        preg_match_all('/\[OK\]: (\S+)/', $display, $matches);
        // Paths are compared in one separator form: Finder joins the names below the include paths natively
        $processed = array_map(static fn(string $path): string => str_replace('\\', '/', $path), $matches[1]);
        sort($processed);
        $appDir = str_replace('\\', '/', $this->appDir);
        $this->assertSame([$appDir . '/src/Some.php', $appDir . '/vendor/acme/lib/src/Acme.php', $appDir . '/vendor/autoload.php'], $processed);
        $this->assertStringContainsString('Total 3 files to process.', $display);
        $this->assertStringContainsString('[DONE]: Total processed 3, 0 errors.', $display);
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

    /**
     * Fails files containing `broken`, raises a warning for `warning` and a silenced one for `silenced`
     */
    private function createFailingTransformer(): SourceTransformer
    {
        return new class implements SourceTransformer {
            public function transform(StreamMetaData $metadata): TransformerResult
            {
                if (str_contains($metadata->source, 'broken')) {
                    throw new RuntimeException('Cannot weave <broken> file');
                }
                if (str_contains($metadata->source, 'warning')) {
                    trigger_error('Weaving warning', E_USER_WARNING);
                }
                if (str_contains($metadata->source, 'silenced')) {
                    @trigger_error('Silenced warning', E_USER_WARNING);
                }

                return TransformerResult::Abstain;
            }
        };
    }

    private function createMemoryOutput(): StreamOutput
    {
        $stream = fopen('php://memory', 'w+');
        assert($stream !== false);

        return new StreamOutput($stream);
    }

    /**
     * Console output writing both standard and error output to memory, to inspect them separately
     */
    private function createConsoleOutput(StreamOutput $errorOutput): ConsoleOutput
    {
        $output = new ConsoleOutput();
        $outputStream = new \ReflectionProperty(StreamOutput::class, 'stream');
        $outputStream->setValue($output, $this->createMemoryOutput()->getStream());
        $output->setErrorOutput($errorOutput);

        return $output;
    }

    private function readStream(StreamOutput $output): string
    {
        rewind($output->getStream());

        return (string) stream_get_contents($output->getStream());
    }

    public function testWarmUpReportsErrorsToErrorOutputAndReturnsTheirCount(): void
    {
        file_put_contents($this->appDir . '/src/Broken.php', "<?php // broken\n");
        file_put_contents($this->appDir . '/src/Warning.php', "<?php // warning\n");
        file_put_contents($this->appDir . '/src/Silenced.php', "<?php // silenced\n");

        $errorOutput = $this->createMemoryOutput();
        $output      = $this->createConsoleOutput($errorOutput);
        $warmer = new CacheWarmer($this->createKernel($this->cacheDir, [$this->createFailingTransformer()]), $output);

        $previousHandler = static fn(): bool => false;
        set_error_handler($previousHandler);
        // PHPUnit lowers error_reporting() while a test runs, the warmer honours the application's level
        $previousLevel = error_reporting(E_ALL);
        try {
            $errors = $warmer->warmUp();
        } finally {
            error_reporting($previousLevel);
            $currentHandler = set_error_handler(null);
            restore_error_handler();
            restore_error_handler();
        }

        $this->assertSame(2, $errors);
        $this->assertSame($previousHandler, $currentHandler, 'The warmer must restore the previous error handler');

        $errorDisplay = $this->readStream($errorOutput);
        $this->assertStringContainsString('Broken.php: Cannot weave <broken> file', $errorDisplay);
        $this->assertStringContainsString('Warning.php: Weaving warning', $errorDisplay);
        $this->assertStringNotContainsString('Silenced', $errorDisplay);

        $display = $this->readStream($output);
        $this->assertStringContainsString('Silenced.php', $display);
        $this->assertStringContainsString('[DONE]: Total processed 4, 2 errors.', $display);
    }

    public function testFailFastStopsAfterFirstError(): void
    {
        // Two broken files: whichever comes first, the warmer stops before the second one
        file_put_contents($this->appDir . '/src/Broken.php', "<?php // broken\n");
        file_put_contents($this->appDir . '/src/AlsoBroken.php', "<?php // broken\n");

        $output = new BufferedOutput();
        $kernel = $this->createKernel($this->cacheDir, [$this->createFailingTransformer()]);
        $warmer = new CacheWarmer($kernel, $output, failFast: true);

        $this->assertSame(1, $warmer->warmUp());
        $this->assertStringContainsString('1 errors.', $output->fetch());
    }
}
