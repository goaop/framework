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

use Go\Aop\Features;
use Go\Core\AdvisorIndex;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\AspectLoaderInterface;
use Go\Core\Container;
use Go\Instrument\PathResolver;
use Go\Instrument\Transformer\SourceTransformer;
use Go\Instrument\Transformer\StreamMetaData;
use Go\Instrument\Transformer\TransformerResult;
use Go\ParserReflection\ReflectionEngine;
use Go\PhpUnit\UsesTemporaryDirectory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use PhpToken;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

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
        int $features = 0,
    ): AspectKernel {
        $aspectLoader = $this->createMock(AspectLoaderInterface::class);
        $aspectLoader->method('getUnloadedAspects')->willReturn([]);

        $kernel = $this->createMock(AspectKernel::class);
        $kernel->method('getOptions')->willReturn([
            'debug'          => true,
            'appDir'         => $this->appDir,
            'cacheDir'       => $cacheDir,
            'cacheFileMode'  => 0770,
            'features'       => $features,
            'includePaths'   => $includePaths,
            'excludePaths'   => $excludePaths,
            'containerClass' => Container::class,
            'parsedFilesLimit' => 100,
        ]);
        $kernel->method('hasFeature')->willReturnCallback(static fn(int $feature): bool => ($features & $feature) !== 0);

        $container = $this->createMock(AspectContainer::class);
        $container->method('getService')->willReturnMap([
            [AspectKernel::class, $kernel],
            [CachePathManager::class, new CachePathManager($kernel)],
            [AdvisorIndex::class, new AdvisorIndex($container, $aspectLoader)],
        ]);
        // Without transformers, streamed files pass through the filter untransformed
        $container->method('getServicesByInterface')->willReturn($transformers);
        // No tracked resource changes: a record is fresh while its source is unchanged
        $container->method('isFreshSince')->willReturn(true);
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
        $output = new BufferedOutput(OutputInterface::VERBOSITY_VERBOSE);
        $warmer = new CacheWarmer($this->createKernel($this->cacheDir), $output);

        $warmer->warmUp();
        $display = $output->fetch();

        $this->assertStringContainsString('Total 1 files to process.', $display);
        $this->assertStringContainsString('[OK]', $display);
        $this->assertStringContainsString('[DONE]: Total processed 1, 0 errors in ', $display);
    }

    /**
     * An output that is not a terminal (a CI log, a file) gets progress lines instead of a list of every file
     */
    public function testWarmUpWritesProgressLinesWhenNotVerbose(): void
    {
        for ($i = 0; $i < 20; $i++) {
            file_put_contents($this->appDir . "/src/File{$i}.php", "<?php\n");
        }
        $output = new BufferedOutput();
        $warmer = new CacheWarmer($this->createKernel($this->cacheDir), $output);

        $this->assertSame(0, $warmer->warmUp());
        $display = $output->fetch();

        $this->assertStringNotContainsString('[OK]', $display);
        $this->assertStringNotContainsString('File3.php', $display);
        $this->assertStringContainsString('21/21 [============================] 100%', $display);
        $this->assertStringContainsString('[DONE]: Total processed 21, 0 errors in ', $display);
    }

    public function testWarmerKeepsTheGivenNumberOfParsedFiles(): void
    {
        $warmer = new CacheWarmer($this->createKernel($this->cacheDir), new BufferedOutput(), cachedFiles: 3);

        $warmer->warmUp();

        $this->assertSame(3, new \ReflectionProperty(ReflectionEngine::class, 'maximumCachedFiles')->getValue());
    }

    /**
     * Without a command line to start them, workers are not used, whatever their number
     */
    public function testWarmUpWithoutWorkerCommandLineWeavesInProcess(): void
    {
        $output = new BufferedOutput();
        $warmer = new CacheWarmer($this->createKernel($this->cacheDir), $output, workers: 4);

        $this->assertSame(0, $warmer->warmUp());
        $display = $output->fetch();

        $this->assertStringNotContainsString('parallel worker processes', $display);
        $this->assertStringContainsString('[DONE]: Total processed 1, 0 errors in ', $display);
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

        $output = new BufferedOutput(OutputInterface::VERBOSITY_VERBOSE);
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
        $this->assertStringContainsString('[DONE]: Total processed 3, 0 errors in ', $display);
    }

    /**
     * Starts tests/Fixtures/warmup/fake-worker.php as the workers: it answers every file with a record of its process
     *
     * @return list<string>
     */
    private static function fakeWorkerCommandLine(): array
    {
        return [PHP_BINARY, __DIR__ . '/../../Fixtures/warmup/fake-worker.php'];
    }

    public function testWorkersWeaveTheFilesAndTheirRecordsAreWrittenOnce(): void
    {
        for ($i = 0; $i < 9; $i++) {
            file_put_contents($this->appDir . "/src/File{$i}.php", "<?php\n" . str_repeat('// padding' . "\n", $i));
        }
        file_put_contents($this->appDir . '/src/error-file.php', "<?php\n");
        $output = new BufferedOutput(OutputInterface::VERBOSITY_VERBOSE);
        $warmer = new CacheWarmer($this->createKernel($this->cacheDir), $output, workers: 3, workerCommandLine: self::fakeWorkerCommandLine());

        $this->assertSame(1, $warmer->warmUp());
        $display = $output->fetch();

        $this->assertStringContainsString('Weaving in 3 parallel worker processes.', $display);
        $this->assertSame(10, substr_count($display, '[OK]'));
        $this->assertStringContainsString('error-file.php: Cannot weave error-file.php', $display);
        $this->assertStringContainsString('[DONE]: Total processed 11, 1 errors in ', $display);
        $this->assertStringContainsString(' with 3 workers.', $display);

        // The records of every worker end up in one metadata file
        $metadata = include $this->cacheDir . '/_transformation.cache';
        $this->assertIsArray($metadata);
        $this->assertIsArray($metadata['files']);
        $this->assertCount(11, $metadata['files']);
        $this->assertArrayHasKey('/File8.php', $metadata['files']);
    }

    public function testInterruptedWorkersGetNoFiles(): void
    {
        $output = new BufferedOutput();
        $warmer = new CacheWarmer($this->createKernel($this->cacheDir), $output, workers: 2, workerCommandLine: self::fakeWorkerCommandLine());
        file_put_contents($this->appDir . '/src/Other.php', "<?php\n");

        $warmer->interrupt();
        $this->assertSame(0, $warmer->warmUp());
        $display = $output->fetch();

        $this->assertStringContainsString('[STOP]: Warmup was interrupted, stopping...', $display);
        $this->assertStringContainsString('[DONE]: Total processed 0, 0 errors in ', $display);
        $this->assertFileDoesNotExist($this->cacheDir . '/_transformation.cache');
    }

    /**
     * No more workers than files
     */
    public function testWorkersAreLimitedByTheNumberOfFiles(): void
    {
        $output = new BufferedOutput();
        $warmer = new CacheWarmer($this->createKernel($this->cacheDir), $output, workers: 8, workerCommandLine: self::fakeWorkerCommandLine());
        file_put_contents($this->appDir . '/src/Other.php', "<?php\n");

        $this->assertSame(0, $warmer->warmUp());
        $this->assertStringContainsString('Weaving in 2 parallel worker processes.', $output->fetch());
    }

    public function testWarmUpIntoAnotherDirectoryLeavesTheDirectoryOfTheKernelAlone(): void
    {
        $otherDir = $this->cacheDir . '-other';
        // A transformer that marks every file, so a woven file lands in the cache
        $transformer = new class implements SourceTransformer {
            public function transform(StreamMetaData $metadata): TransformerResult
            {
                $metadata->setTokenStreamFromRawTokens(...PhpToken::tokenize($metadata->source . '// woven'));

                return TransformerResult::Transformed;
            }
        };
        $output = new BufferedOutput();
        $warmer = new CacheWarmer($this->createKernel($this->cacheDir, [$transformer]), $output, cacheDir: $otherDir);

        $this->assertSame(0, $warmer->warmUp());

        $this->assertStringContainsString('[DONE]: Total processed 1, 0 errors', $output->fetch());
        $this->assertFileExists($otherDir . '/src/Some.php');
        $this->assertFileExists($otherDir . '/_include.cache');
        $this->assertFileDoesNotExist($this->cacheDir . '/src/Some.php');
        $this->assertFileDoesNotExist($this->cacheDir . '/_include.cache');
        self::removeTemporaryDirectory($otherDir);
    }

    public function testWarmUpInPlaceCreatesTheDirectoryOfAPrebuiltCache(): void
    {
        $cacheDir = $this->cacheDir . '/aop';
        // A kernel with a prebuilt cache never creates its directory: the deployment builds it, with this warm-up
        $warmer = new CacheWarmer($this->createKernel($cacheDir, features: Features::PREBUILT_CACHE), new BufferedOutput());

        $this->assertSame(0, $warmer->warmUp());

        $this->assertFileExists($cacheDir . '/_include.cache', 'The files are recorded, also when nothing was woven');
    }

    public function testWarmUpInPlaceOverAPrebuiltCacheRefreshesChangedFilesOnly(): void
    {
        $otherFile = $this->appDir . '/src/Other.php';
        file_put_contents($otherFile, "<?php echo 'other';\n");
        $transformer = new class implements SourceTransformer {
            /** @var list<string> */
            public array $wovenFiles = [];

            public function transform(StreamMetaData $metadata): TransformerResult
            {
                // In one separator form: the woven files are resolved natively
                $this->wovenFiles[] = str_replace('\\', '/', $metadata->uri);
                $metadata->setTokenStreamFromRawTokens(...PhpToken::tokenize($metadata->source . '// woven'));

                return TransformerResult::Transformed;
            }
        };
        $kernel = $this->createKernel($this->cacheDir, [$transformer], features: Features::PREBUILT_CACHE);
        $this->assertSame(0, new CacheWarmer($kernel, new BufferedOutput())->warmUp());
        $sourceFile = str_replace('\\', '/', $this->sourceFile);
        $this->assertSame([str_replace('\\', '/', $otherFile), $sourceFile], $transformer->wovenFiles);

        // The deployment changes one source and warms the prebuilt cache up again, in place
        file_put_contents($this->sourceFile, "<?php echo 'changed';\n");
        touch($this->sourceFile, time() + 10);
        $transformer->wovenFiles = [];
        $this->assertSame(0, new CacheWarmer($kernel, new BufferedOutput())->warmUp());

        $this->assertSame([$sourceFile], $transformer->wovenFiles, 'Only the changed file is woven again');
        $this->assertSame("<?php echo 'changed';\n// woven", file_get_contents($this->cacheDir . '/src/Some.php'));
        $this->assertSame("<?php echo 'other';\n// woven", file_get_contents($this->cacheDir . '/src/Other.php'));
        $this->assertFalse(
            $kernel->getContainer()->getService(CachePathManager::class)->isBuildingCache,
            'Requests trust the prebuilt cache again',
        );
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
        $this->assertStringContainsString('[DONE]: Total processed 0, 0 errors in ', $display);
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
        $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
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
        $this->assertStringContainsString('[DONE]: Total processed 4, 2 errors in ', $display);
    }

    public function testFailFastStopsAfterFirstError(): void
    {
        // Next to the valid Some.php from setUp(), two broken files. Files are woven in path order, whatever order
        // the file system lists them in: AlsoBroken.php fails first, and the warmer stops before the other two
        file_put_contents($this->appDir . '/src/Broken.php', "<?php // broken\n");
        file_put_contents($this->appDir . '/src/AlsoBroken.php', "<?php // broken\n");

        $output = new BufferedOutput();
        $kernel = $this->createKernel($this->cacheDir, [$this->createFailingTransformer()]);
        $warmer = new CacheWarmer($kernel, $output, failFast: true);

        $this->assertSame(1, $warmer->warmUp());
        $display = $output->fetch();
        $this->assertStringContainsString('[STOP]: Warmup was interrupted, stopping...', $display);
        $this->assertStringContainsString('AlsoBroken.php: Cannot weave <broken> file', $display);
        $this->assertDoesNotMatchRegularExpression('#[/\\\\]Broken\.php: Cannot weave#', $display);
        $this->assertStringContainsString('Total processed 1, 1 errors in ', $display);
    }

    public function testPreloadScriptIsWrittenIntoTheCacheDirectoryAfterACompleteWarmUp(): void
    {
        $output = new BufferedOutput();
        $warmer = new CacheWarmer($this->createKernel($this->cacheDir), $output, preloadFile: '');

        $this->assertSame(0, $warmer->warmUp());

        $script = $this->cacheDir . '/_preload.php';
        $this->assertFileExists($script);
        // The warmer reports the resolved path, with the native separators of the platform: both sides use `/`
        $this->assertStringContainsString(
            'Preload script written to ' . str_replace('\\', '/', $script),
            str_replace('\\', '/', $output->fetch()),
        );
        $content = (string) file_get_contents($script);
        $autoloadFile = ComposerAutoloader::ofProcess()->autoloadFile;
        if (PathResolver::relativePath(dirname($script), $autoloadFile) !== null) {
            $this->assertStringContainsString("\$loader = require __DIR__ . '", $content);
            $this->assertStringContainsString("/vendor/autoload.php';", $content);
        } else {
            // The temporary directory lies on another drive than the framework (Windows CI): no relative path exists
            $this->assertStringContainsString('$loader = require ' . var_export($autoloadFile, true) . ';', $content);
        }
        $this->assertStringContainsString("define('AOP_CACHE_DIR', __DIR__)", $content);
        $this->assertStringContainsString("define('AOP_ROOT_DIR', __DIR__ . '/../" . basename($this->appDir) . "')", $content);
    }

    public function testPreloadScriptIsWrittenToTheGivenFile(): void
    {
        $script = $this->appDir . '/config/preload.php';
        $warmer = new CacheWarmer($this->createKernel($this->cacheDir), new BufferedOutput(), preloadFile: $script);

        $this->assertSame(0, $warmer->warmUp());

        $this->assertFileExists($script);
        $this->assertFileDoesNotExist($this->cacheDir . '/_preload.php');
        $this->assertStringContainsString(
            "define('AOP_CACHE_DIR', __DIR__ . '/../../" . basename($this->cacheDir) . "')",
            (string) file_get_contents($script),
        );
    }

    public function testPreloadScriptIsNotWrittenWhenAFileFailed(): void
    {
        file_put_contents($this->appDir . '/src/Broken.php', "<?php // broken\n");
        $warmer = new CacheWarmer($this->createKernel($this->cacheDir, [$this->createFailingTransformer()]), new BufferedOutput(), preloadFile: '');

        $this->assertSame(1, $warmer->warmUp());

        $this->assertFileDoesNotExist($this->cacheDir . '/_preload.php', 'An incomplete cache is not preloaded');
    }

    public function testPreloadScriptIsNotWrittenWhenInterrupted(): void
    {
        $warmer = new CacheWarmer($this->createKernel($this->cacheDir), new BufferedOutput(), preloadFile: '');
        $warmer->interrupt();

        $this->assertSame(0, $warmer->warmUp());

        $this->assertFileDoesNotExist($this->cacheDir . '/_preload.php');
    }
}
