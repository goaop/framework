<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\ClassLoading;

use Go\Aop\Exception\InvalidConfigurationException;
use Go\Console\Warmup\WarmupReporter;
use Go\Console\Warmup\WarmupReporterFactory;
use Go\Core\AdvisorIndex;
use Go\Core\AspectKernel;
use Go\Instrument\ClassLoading\Warmup\CpuCoreCounter;
use Go\Instrument\ClassLoading\Warmup\FileWarmer;
use Go\Instrument\ClassLoading\Warmup\WorkerPool;
use Go\Instrument\FileSystem\Enumerator;
use SplFileInfo;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

use function count;

/**
 * Warms up the cache
 *
 * Files are woven in this process, or in parallel worker processes: this process then hands the files out to the
 * workers, merges the cache records they report and writes the cache metadata once (see WorkerPool).
 *
 * @internal Used by the cache:warmup:aop command
 */
class CacheWarmer
{
    /**
     * Number of workers that means one per CPU core, as long as every worker gets enough files
     */
    public const int AUTO_WORKERS = 0;

    /**
     * Fewest files per worker the automatic number of workers allows: starting a worker boots the kernel again,
     * which pays off only when the worker weaves enough files
     */
    private const int MIN_FILES_PER_AUTO_WORKER = 50;

    /**
     * Whether the warmup loop was asked to stop (e.g. by a signal handler)
     */
    private bool $interrupted = false;

    /**
     * CacheWarmer constructor.
     */
    public function __construct(
        /** Instance of aspect kernel */
        protected AspectKernel $aspectKernel,
        /** Output instance, errors and the progress go to its error output when it has one */
        protected OutputInterface $output = new NullOutput(),
        /** Whether to stop after the first file that fails to process */
        protected bool $failFast = false,
        /** Number of worker processes, {@see self::AUTO_WORKERS} for one per CPU core, 1 weaves in this process */
        protected int $workers = 1,
        /** @var list<string> Command line that starts one worker process (WarmupWorker::commandLine()), none weaves in this process */
        protected array $workerCommandLine = [],
    ) {}

    /**
     * Asks the warmup loop to stop cleanly before processing the next file
     */
    public function interrupt(): void
    {
        $this->interrupted = true;
    }

    /**
     * Warms up cache
     *
     * @return int Number of files that failed to process
     */
    public function warmUp(): int
    {
        $options = $this->aspectKernel->getOptions();

        if (empty($options['cacheDir'])) {
            throw InvalidConfigurationException::cacheWarmerWithoutCacheDir();
        }

        // One walk of the application tree: the collected list is counted and then processed
        $enumerator = new Enumerator($options['appDir'], $options['includePaths'], $options['excludePaths']);
        $files      = iterator_to_array($enumerator->enumerate(), false);
        $workers    = $this->resolveWorkerCount(count($files));

        $reporter = WarmupReporterFactory::create($this->output, $options['appDir']);
        $reporter->start(count($files), $workers);

        $processed = 0;
        $errors    = 0;
        $onResult  = function (string $file, ?string $error) use ($reporter, &$processed, &$errors): void {
            $processed++;
            if ($error !== null) {
                $errors++;
                if ($this->failFast) {
                    $this->interrupt();
                }
            }
            $reporter->advance($file, $error);
        };

        if ($workers > 1) {
            $this->warmUpInWorkers($files, $workers, $reporter, $onResult);
        } else {
            $this->warmUpInProcess($files, $reporter, $onResult);
        }

        $reporter->finish($processed, $errors);

        return $errors;
    }

    /**
     * Returns the number of worker processes for the given number of files, 1 to weave in this process
     */
    private function resolveWorkerCount(int $fileCount): int
    {
        if ($this->workerCommandLine === []) {
            return 1;
        }
        $workers = $this->workers;
        if ($workers === self::AUTO_WORKERS) {
            $workers = min(CpuCoreCounter::count(), intdiv($fileCount, self::MIN_FILES_PER_AUTO_WORKER));
        }

        return max(1, min($workers, $fileCount));
    }

    /**
     * @param list<SplFileInfo>                  $files
     * @param \Closure(string, ?string): void    $onResult
     */
    private function warmUpInProcess(array $files, WarmupReporter $reporter, \Closure $onResult): void
    {
        // The transformation pipeline is registered lazily; the filter URIs used by FileWarmer bypass
        // FilterInjectorTransformer::rewrite(), so bring it up explicitly
        SourceTransformingLoader::ensureRegistered($this->aspectKernel->getContainer());

        $fileWarmer = new FileWarmer();
        foreach ($files as $file) {
            if ($this->interrupted) {
                $reporter->interrupt();
                break;
            }
            $path = self::pathOf($file);
            $onResult($path, $fileWarmer->warmUp($path));
        }
    }

    /**
     * @param list<SplFileInfo>                  $files
     * @param \Closure(string, ?string): void    $onResult
     */
    private function warmUpInWorkers(array $files, int $workers, WarmupReporter $reporter, \Closure $onResult): void
    {
        $container        = $this->aspectKernel->getContainer();
        $cachePathManager = $container->getService(CachePathManager::class);
        // Loads every aspect once, which writes their advisor caches before the workers need them
        $container->getService(AdvisorIndex::class)->getAdvisors();

        // Largest files first: they take longest, and the small ones left for the end even out the workers
        usort($files, static fn(SplFileInfo $left, SplFileInfo $right): int => $right->getSize() <=> $left->getSize());
        $paths = array_map(self::pathOf(...), $files);

        $stopReported = false;
        $isStopped    = function () use ($reporter, &$stopReported): bool {
            if ($this->interrupted && !$stopReported) {
                $stopReported = true;
                $reporter->interrupt();
            }

            return $this->interrupted;
        };

        try {
            new WorkerPool($this->workerCommandLine, $workers)->run(
                $paths,
                static function (string $file, ?string $error, array $records) use ($cachePathManager, $onResult): void {
                    $cachePathManager->mergeCacheState($records);
                    $onResult($file, $error);
                },
                $isStopped,
            );
        } finally {
            // The records of every processed file are kept, also when the warmup was interrupted or failed
            $cachePathManager->flushCacheState();
        }
    }

    /**
     * Returns the real path of an enumerated file, its path when it has none (a stream wrapper)
     */
    private static function pathOf(SplFileInfo $file): string
    {
        $realPath = $file->getRealPath();

        return $realPath !== false ? $realPath : $file->getPathname();
    }
}
