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

/**
 * A cache warmup worker process, as seen by the coordinator
 *
 * Tracks the files sent to the worker and not answered yet: the worker answers them in order, so when it dies, the
 * first of them is the file that killed it.
 *
 * @internal Used by WorkerPool
 */
final class WorkerProcess
{
    /**
     * Number of bytes of the diagnostic output that are kept for an error message
     */
    private const int DIAGNOSTICS_LIMIT = 4096;

    /**
     * Files sent to the worker and not answered yet, in the order the worker processes them
     *
     * @var list<string>
     */
    public private(set) array $pendingFiles = [];

    /**
     * Whether the worker booted the kernel and is ready to weave
     */
    public private(set) bool $ready = false;

    /**
     * Whether the worker closed its output: it exited, or is about to
     */
    public private(set) bool $finished = false;

    /**
     * Output of the worker that is not a message (PHP errors, stray output), only its tail is kept
     */
    public private(set) string $diagnostics = '';

    /**
     * Incomplete last line of the output
     */
    private string $buffer = '';

    private bool $inputClosed = false;

    private bool $errorOutputClosed = false;

    private function __construct(
        /** @var resource Process handle */
        private readonly mixed $process,
        /** @var resource Input of the worker */
        private readonly mixed $input,
        /** @var resource Message output of the worker */
        private readonly mixed $output,
        /** @var resource Error output of the worker */
        private readonly mixed $errorOutput,
    ) {}

    /**
     * Starts a worker process
     *
     * @param list<string> $commandLine
     */
    public static function start(array $commandLine): self
    {
        // stream_select() can wait on sockets only on Windows, pipes everywhere else
        $outputSpec  = PHP_OS_FAMILY === 'Windows' ? ['socket'] : ['pipe', 'w'];
        $descriptors = [0 => ['pipe', 'r'], 1 => $outputSpec, 2 => $outputSpec];
        $process     = proc_open($commandLine, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw WeavingException::warmupWorkerNotStarted(implode(' ', $commandLine));
        }
        /** @var array{resource, resource, resource} $pipes */
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        return new self($process, $pipes[0], $pipes[1], $pipes[2]);
    }

    /**
     * Sends a file to weave
     */
    public function send(string $file): void
    {
        $this->pendingFiles[] = $file;
        // A worker that died meanwhile is detected by the end of its output, a failed write tells nothing more
        @fwrite($this->input, WorkerProtocol::encode(['file' => $file]));
    }

    /**
     * Closes the input of the worker: it finishes the files sent so far and exits
     */
    public function closeInput(): void
    {
        if (!$this->inputClosed) {
            $this->inputClosed = true;
            fclose($this->input);
        }
    }

    /**
     * Returns the streams to wait for with stream_select()
     *
     * @return list<resource>
     */
    public function streams(): array
    {
        $streams = $this->finished ? [] : [$this->output];
        if (!$this->errorOutputClosed) {
            $streams[] = $this->errorOutput;
        }

        return $streams;
    }

    /**
     * Reads what the worker wrote so far and returns the answers for files
     *
     * @return list<array{file: string, error: string|null, records: array<string, array<string, mixed>>}>
     */
    public function read(): array
    {
        $this->readErrorOutput();
        while (($chunk = fread($this->output, 65536)) !== false && $chunk !== '') {
            $this->buffer .= $chunk;
        }
        $lines = explode("\n", $this->buffer);
        $this->buffer = array_pop($lines);
        if (feof($this->output)) {
            $this->finished = true;
            $lines[] = $this->buffer;
            $this->buffer = '';
        }

        $results = [];
        foreach ($lines as $line) {
            $message = WorkerProtocol::decode($line);
            if ($message === null) {
                $this->addDiagnostics($line === '' ? '' : $line . "\n");
                continue;
            }
            $result = $this->handleMessage($message);
            if ($result !== null) {
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * Waits for the exit of the worker and returns its exit code
     */
    public function close(): int
    {
        $this->closeInput();
        // The rest of the error output, e.g. a fatal error, explains why a worker exited: read up to its end
        if (!$this->errorOutputClosed) {
            stream_set_blocking($this->errorOutput, true);
        }
        $this->readErrorOutput();
        fclose($this->output);

        return proc_close($this->process);
    }

    /**
     * Kills the worker, when the warmup is aborted
     */
    public function terminate(): void
    {
        proc_terminate($this->process);
        $this->close();
    }

    /**
     * @param array<string, mixed> $message
     *
     * @return array{file: string, error: string|null, records: array<string, array<string, mixed>>}|null
     */
    private function handleMessage(array $message): ?array
    {
        switch ($message['type'] ?? null) {
            case 'ready':
                $this->ready = true;
                break;
            case 'failed':
                $this->addDiagnostics(is_string($message['error'] ?? null) ? $message['error'] . "\n" : '');
                break;
            case 'result':
                $file   = $message['file'] ?? null;
                $index  = array_search($file, $this->pendingFiles, true);
                if (!is_string($file) || $index === false) {
                    break;
                }
                array_splice($this->pendingFiles, $index, 1);
                $error = $message['error'] ?? null;
                /** @var array<string, array<string, mixed>> $records Collected by CachePathManager::takeNewCacheState() of the worker */
                $records = $message['records'] ?? [];

                return ['file' => $file, 'error' => is_string($error) ? $error : null, 'records' => $records];
        }

        return null;
    }

    private function readErrorOutput(): void
    {
        while (!$this->errorOutputClosed && ($chunk = fread($this->errorOutput, 65536)) !== false && $chunk !== '') {
            $this->addDiagnostics($chunk);
        }
        if (!$this->errorOutputClosed && feof($this->errorOutput)) {
            $this->errorOutputClosed = true;
            fclose($this->errorOutput);
        }
    }

    private function addDiagnostics(string $text): void
    {
        $this->diagnostics = substr($this->diagnostics . $text, -self::DIAGNOSTICS_LIMIT);
    }
}
