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

use Closure;
use Go\Aop\Exception\WeavingException;

/**
 * Weaves files in parallel worker processes
 *
 * The coordinator (this process) owns the queue of files and hands them out on demand: every worker gets a new file
 * as soon as it answered one, so a worker busy with large files gets fewer of them and all workers finish at about the
 * same time. Each worker is kept {@see self::FILES_IN_FLIGHT} files ahead, so it never waits for the coordinator.
 *
 * The workers report the cache records of every file, the coordinator merges them (see CacheWarmer) and writes the
 * cache metadata once. A worker that dies fails the file it was weaving, its other files go back to the queue and a
 * new worker takes over.
 *
 * @internal Used by CacheWarmer
 */
final class WorkerPool
{
    /**
     * Number of files sent to a worker ahead of its answers
     */
    private const int FILES_IN_FLIGHT = 2;

    public function __construct(
        /** @var list<string> Command line that starts one worker process, see WarmupWorker::commandLine() */
        private readonly array $commandLine,
        /** Number of worker processes */
        private readonly int $size,
    ) {}

    /**
     * Weaves the files and reports every processed file
     *
     * @param list<string>                                                                   $files     Files to weave, handed out in this order
     * @param Closure(string $file, string|null $error, array<string, array<string, mixed>> $records): void $onResult  Receives every processed file
     * @param Closure(): bool                                                                $isStopped Whether to stop handing out files: the workers finish the files they got
     */
    public function run(array $files, Closure $onResult, Closure $isStopped): void
    {
        /** @var array<int, WorkerProcess> $workers */
        $workers = [];
        // Files of dead workers are handed out first
        $retries    = [];
        $nextFile   = 0;
        $fileCount  = count($files);
        $spareStarts = $this->size;

        try {
            for ($i = 0; $i < min($this->size, $fileCount); $i++) {
                $workers[] = WorkerProcess::start($this->commandLine);
            }

            while ($workers !== []) {
                $stopped = $isStopped();
                foreach ($workers as $worker) {
                    while (!$stopped && count($worker->pendingFiles) < self::FILES_IN_FLIGHT) {
                        if ($retries !== []) {
                            $worker->send(array_shift($retries));
                        } elseif ($nextFile < $fileCount) {
                            $worker->send($files[$nextFile++]);
                        } else {
                            break;
                        }
                    }
                    if ($worker->pendingFiles === [] && ($stopped || ($retries === [] && $nextFile >= $fileCount))) {
                        $worker->closeInput();
                    }
                }

                $this->waitForOutput($workers);

                foreach ($workers as $key => $worker) {
                    foreach ($worker->read() as $result) {
                        $onResult($result['file'], $result['error'], $result['records']);
                    }
                    if (!$worker->finished) {
                        continue;
                    }

                    unset($workers[$key]);
                    $exitCode = $worker->close();
                    if (!$worker->ready) {
                        // The kernel could not be booted: every other worker fails the same way
                        throw WeavingException::warmupWorkerFailedToStart($exitCode, $worker->diagnostics);
                    }
                    if ($worker->pendingFiles === []) {
                        continue;
                    }

                    // The worker died: the first pending file was being woven, the others were not touched yet
                    $pendingFiles = $worker->pendingFiles;
                    $crashedFile  = array_shift($pendingFiles);
                    $onResult($crashedFile, self::describeCrash($exitCode, $worker->diagnostics), []);
                    array_push($retries, ...$pendingFiles);

                    // Every crash fails a file, so the replacements are bounded by the number of files; a crash of an
                    // idle worker fails none and uses one of the spare starts
                    $hasWork = $retries !== [] || $nextFile < $fileCount;
                    if ($hasWork && !$isStopped()) {
                        $workers[] = WorkerProcess::start($this->commandLine);
                    }
                }
                // A worker that died between two files leaves its queue to the others; restart one if none is left
                if ($workers === [] && ($retries !== [] || $nextFile < $fileCount) && !$isStopped() && $spareStarts-- > 0) {
                    $workers[] = WorkerProcess::start($this->commandLine);
                }
            }
        } finally {
            foreach ($workers as $worker) {
                $worker->terminate();
            }
        }
    }

    /**
     * Waits until a worker writes something, or a second passes
     *
     * @param array<int, WorkerProcess> $workers
     */
    private function waitForOutput(array $workers): void
    {
        $read = [];
        foreach ($workers as $worker) {
            array_push($read, ...$worker->streams());
        }
        if ($read === []) {
            return;
        }
        $write  = null;
        $except = null;
        // Interrupted by a signal (Ctrl+C) it returns false: the loop checks whether it should stop and waits again
        @stream_select($read, $write, $except, 1);
    }

    private static function describeCrash(int $exitCode, string $diagnostics): string
    {
        $diagnostics = trim($diagnostics);

        return sprintf('Worker process exited unexpectedly with code %d', $exitCode)
            . ($diagnostics !== '' ? ': ' . $diagnostics : '');
    }
}
