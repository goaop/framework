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

namespace Go\Instrument\ClassLoading\Warmup;

use Go\PhpUnit\UsesTemporaryDirectory;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Runs the worker loop in the test process, with message streams in memory
 */
// Separate processes: the worker boots a kernel (a process-wide singleton) and registers the stream filter
#[RunTestsInSeparateProcesses]
final class WarmupWorkerTest extends TestCase
{
    use UsesTemporaryDirectory;

    private string $appDir;
    private string $cacheDir;
    private string $loader;

    protected function setUp(): void
    {
        $this->appDir   = self::createTemporaryDirectory('worker-app');
        $this->cacheDir = self::createTemporaryDirectory('worker-cache');
        mkdir($this->appDir . '/src');
        file_put_contents($this->appDir . '/src/Some.php', "<?php echo 'original';\n");

        // A loader of a minimal application: a kernel without aspects
        $this->loader = $this->appDir . '/loader.php';
        file_put_contents($this->loader, sprintf(<<<'PHP'
            <?php
            echo 'Output of the loader';
            final class WarmupWorkerTestKernel extends \Go\Core\AspectKernel
            {
                protected function configureAop(\Go\Core\AspectContainer $container): void {}
            }
            WarmupWorkerTestKernel::getInstance()->init([
                'debug'        => true,
                'appDir'       => %s,
                'cacheDir'     => %s,
                'includePaths' => [%s],
            ]);
            PHP, var_export($this->appDir, true), var_export($this->cacheDir, true), var_export($this->appDir . '/src', true)));
    }

    protected function tearDown(): void
    {
        self::removeTemporaryDirectory($this->cacheDir);
        self::removeTemporaryDirectory($this->appDir);
    }

    /**
     * @param list<string> $lines
     *
     * @return resource
     */
    private static function createStream(array $lines = [])
    {
        $stream = fopen('php://memory', 'w+');
        assert($stream !== false);
        fwrite($stream, implode('', $lines));
        rewind($stream);

        return $stream;
    }

    /**
     * @param resource $stream
     *
     * @return list<array<string, mixed>>
     */
    private static function readMessages($stream): array
    {
        rewind($stream);
        $messages = [];
        while (($line = fgets($stream)) !== false) {
            $message = WorkerProtocol::decode($line);
            self::assertNotNull($message, 'The worker writes nothing but messages: ' . $line);
            $messages[] = $message;
        }

        return $messages;
    }

    public function testWorkerAnswersEveryFileWithItsCacheRecords(): void
    {
        $file    = $this->appDir . '/src/Some.php';
        $missing = $this->appDir . '/src/Missing.php';
        $input   = self::createStream([
            WorkerProtocol::encode(['file' => $file]),
            "garbage line\n",
            WorkerProtocol::encode(['file' => $missing]),
        ]);
        $output  = self::createStream();
        $obLevel = ob_get_level();

        // PHPUnit lowers error_reporting() while a test runs, the worker honours the application's level
        $previousLevel = error_reporting(E_ALL);
        try {
            $exitCode = WarmupWorker::main($this->loader, $input, $output);
        } finally {
            error_reporting($previousLevel);
        }

        $this->assertSame(0, $exitCode);
        $this->assertSame($obLevel, ob_get_level(), 'The worker closes its output buffer');
        $messages = self::readMessages($output);
        $this->assertCount(3, $messages);
        $this->assertSame(['type' => 'ready'], $messages[0]);

        $this->assertSame('result', $messages[1]['type']);
        $this->assertSame($file, $messages[1]['file']);
        $this->assertNull($messages[1]['error']);
        $this->assertIsArray($messages[1]['records']);
        // Record keys are relative paths with the separators of the system
        $this->assertSame(['/src/Some.php'], array_map(static fn(int|string $key): string => str_replace('\\', '/', (string) $key), array_keys($messages[1]['records'])));

        $this->assertSame($missing, $messages[2]['file']);
        $this->assertIsString($messages[2]['error']);
        $this->assertSame([], $messages[2]['records']);

        $this->assertFileDoesNotExist($this->cacheDir . '/_transformation.cache', 'The coordinator writes the metadata');
    }

    public function testWorkerReportsAKernelThatCanNotBeBooted(): void
    {
        $output = self::createStream();

        $exitCode = WarmupWorker::main($this->appDir . '/missing-loader.php', self::createStream(), $output);

        $this->assertSame(1, $exitCode);
        $messages = self::readMessages($output);
        $this->assertCount(1, $messages);
        $this->assertSame('failed', $messages[0]['type']);
        $this->assertIsString($messages[0]['error']);
        $this->assertStringContainsString('missing-loader.php does not exist or is not readable', $messages[0]['error']);
    }

    public function testCommandLineRunsTheWorkerWithTheLoader(): void
    {
        $commandLine = WarmupWorker::commandLine($this->loader);

        $this->assertSame(PHP_BINARY, $commandLine[0]);
        $this->assertContains('-r', $commandLine);
        $this->assertContains('display_errors=stderr', $commandLine);
        $this->assertSame((string) realpath($this->loader), end($commandLine));
        $this->assertFileExists($commandLine[count($commandLine) - 2], 'The composer autoloader');
    }
}
