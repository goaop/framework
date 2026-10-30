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

use Composer\Autoload\ClassLoader;
use Go\Console\KernelLoader;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\Instrument\ClassLoading\SourceTransformingLoader;
use ReflectionClass;
use Throwable;

/**
 * Worker process of a parallel cache warmup
 *
 * Boots the kernel with the loader of the application, like the console command does, then weaves the files the
 * coordinator sends to its input and answers every file with the outcome and the cache records it produced. The
 * worker never writes the cache metadata files itself: the coordinator merges the records of all workers and writes
 * them once, so the workers can not overwrite each other's records.
 *
 * @internal Started by WorkerPool for CacheWarmer
 */
final class WarmupWorker
{
    /**
     * PHP code of a worker process, run with `php -r`: arguments are the composer autoloader and the loader file
     */
    private const string BOOTSTRAP_CODE = 'require $argv[1]; exit(\\' . self::class . '::main($argv[2]));';

    /**
     * Returns the command line that starts a worker process for the given loader file
     *
     * The worker runs the same PHP binary with the memory limit of this process.
     *
     * @return list<string> Command line, or an empty list when a worker process can not be started from here
     */
    public static function commandLine(string $loader): array
    {
        $classLoaderFile = class_exists(ClassLoader::class, false) ? new ReflectionClass(ClassLoader::class)->getFileName() : false;
        if ($classLoaderFile === false || !function_exists('proc_open')) {
            return [];
        }

        return [
            PHP_BINARY,
            '-d',
            'memory_limit=' . ini_get('memory_limit'),
            // A fatal error must not end up in the message stream
            '-d',
            'display_errors=stderr',
            '-r',
            self::BOOTSTRAP_CODE,
            '--',
            dirname($classLoaderFile, 2) . '/autoload.php',
            KernelLoader::resolve($loader),
        ];
    }

    /**
     * Runs the worker until its input is closed
     *
     * @param resource|null $input  Stream of the messages from the coordinator, STDIN by default
     * @param resource|null $output Stream of the messages to the coordinator, STDOUT by default
     *
     * @return int Exit code of the process
     */
    public static function main(string $loader, mixed $input = null, mixed $output = null): int
    {
        $input  ??= STDIN;
        $output ??= STDOUT;

        // Anything the application prints is discarded, messages are written to the stream directly
        ob_start(static fn(): string => '');
        try {
            return self::serve($loader, $input, $output);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * @param resource $input
     * @param resource $output
     */
    private static function serve(string $loader, mixed $input, mixed $output): int
    {
        // Ctrl+C reaches the whole process group: the coordinator decides when to stop, by closing the input,
        // and the worker finishes the file at hand first
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, SIG_IGN);
        }

        try {
            // The kernel refuses to start without a cache directory
            $container = KernelLoader::load($loader)->getContainer();
            SourceTransformingLoader::ensureRegistered($container);
            $cachePathManager = $container->getService(CachePathManager::class);
        } catch (Throwable $e) {
            fwrite($output, WorkerProtocol::encode(['type' => 'failed', 'error' => $e->getMessage()]));

            return 1;
        }

        fwrite($output, WorkerProtocol::encode(['type' => 'ready']));

        $fileWarmer = new FileWarmer();
        while (($line = fgets($input)) !== false) {
            $file = WorkerProtocol::decode($line)['file'] ?? null;
            if (!is_string($file)) {
                continue;
            }
            $error = $fileWarmer->warmUp($file);
            fwrite($output, WorkerProtocol::encode([
                'type'    => 'result',
                'file'    => $file,
                'error'   => $error,
                'records' => $cachePathManager->takeNewCacheState(),
            ]));
        }

        return 0;
    }
}
