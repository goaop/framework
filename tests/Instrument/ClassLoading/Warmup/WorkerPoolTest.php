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

use Go\Aop\Exception\WeavingException;
use PHPUnit\Framework\TestCase;

/**
 * Drives real worker processes: tests/Fixtures/warmup/fake-worker.php speaks the protocol without booting a kernel
 */
final class WorkerPoolTest extends TestCase
{
    /**
     * Results by file
     *
     * @var array<string, array{error: string|null, records: array<string, array<string, mixed>>}>
     */
    private array $results = [];

    /**
     * @return list<string>
     */
    private static function workerCommandLine(string ...$arguments): array
    {
        return [PHP_BINARY, __DIR__ . '/../../../Fixtures/warmup/fake-worker.php', ...array_values($arguments)];
    }

    /**
     * @param list<string> $files
     */
    private function runPool(WorkerPool $pool, array $files, ?\Closure $isStopped = null): void
    {
        $pool->run(
            $files,
            function (string $file, ?string $error, array $records): void {
                $this->assertArrayNotHasKey($file, $this->results, 'Every file is reported once');
                $this->results[$file] = ['error' => $error, 'records' => $records];
            },
            $isStopped ?? static fn(): bool => false,
        );
    }

    /**
     * @return list<int>
     */
    private function workerProcessIds(): array
    {
        $processIds = [];
        foreach ($this->results as $result) {
            foreach ($result['records'] as $record) {
                $processIds[] = $record['pid'];
            }
        }
        /** @var list<int> $processIds */
        return array_values(array_unique($processIds));
    }

    public function testEveryFileIsWovenOnceAcrossTheWorkers(): void
    {
        $files = array_map(static fn(int $i): string => "/app/File{$i}.php", range(1, 25));

        $this->runPool(new WorkerPool(self::workerCommandLine(), 3), $files);

        $this->assertEqualsCanonicalizing($files, array_keys($this->results));
        $this->assertSame([null], array_values(array_unique(array_column($this->results, 'error'))));
        $this->assertSame(['/File7.php' => ['pid' => $this->results['/app/File7.php']['records']['/File7.php']['pid']]], $this->results['/app/File7.php']['records']);
        $this->assertCount(3, $this->workerProcessIds(), 'Each worker gets files from the start');
    }

    public function testErrorsOfFilesAreReported(): void
    {
        $this->runPool(new WorkerPool(self::workerCommandLine(), 2), ['/app/Good.php', '/app/error-file.php']);

        $this->assertNull($this->results['/app/Good.php']['error']);
        $this->assertSame('Cannot weave error-file.php', $this->results['/app/error-file.php']['error']);
    }

    /**
     * The file a worker died on fails with what the worker printed; its other files go to a new worker
     */
    public function testCrashedWorkerFailsItsFileAndIsReplaced(): void
    {
        $files = ['/app/crash-one.php', '/app/A.php', '/app/B.php', '/app/C.php', '/app/crash-two.php', '/app/D.php'];

        $this->runPool(new WorkerPool(self::workerCommandLine(), 1), $files);

        $this->assertEqualsCanonicalizing($files, array_keys($this->results));
        foreach (['crash-one.php', 'crash-two.php'] as $crashed) {
            $error = (string) $this->results['/app/' . $crashed]['error'];
            $this->assertStringContainsString('Worker process exited unexpectedly with code 255', $error);
            $this->assertStringContainsString("PHP Fatal error: weaving {$crashed} crashed", $error);
        }
        foreach (['A', 'B', 'C', 'D'] as $name) {
            $this->assertNull($this->results["/app/{$name}.php"]['error']);
        }
        // The first worker died on its first file, a new one took over after each crash
        $this->assertCount(2, $this->workerProcessIds());
    }

    public function testStoppedPoolHandsOutNoMoreFiles(): void
    {
        $files = array_map(static fn(int $i): string => "/app/File{$i}.php", range(1, 50));

        $this->runPool(new WorkerPool(self::workerCommandLine(), 2), $files, fn(): bool => $this->results !== []);

        // The workers finish the files they got before the stop, at most two each
        $this->assertGreaterThanOrEqual(1, count($this->results));
        $this->assertLessThanOrEqual(4, count($this->results));
    }

    public function testWorkerThatCanNotBootAbortsTheWarmup(): void
    {
        $this->expectException(WeavingException::class);
        $this->expectExceptionMessageMatches('/exited with code 3 before it was ready to weave:\n(?=.*PHP Warning: something on the way)(?=.*Kernel boot failure)/s');

        $this->runPool(new WorkerPool(self::workerCommandLine('broken'), 2), ['/app/A.php', '/app/B.php']);
    }

    public function testNoWorkerIsStartedWithoutFiles(): void
    {
        $this->runPool(new WorkerPool(self::workerCommandLine('broken'), 2), []);

        $this->assertSame([], $this->results);
    }
}
