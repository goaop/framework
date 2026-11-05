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
use Go\Console\Warmup\FileListReporter;
use Go\Console\Warmup\ProgressBarReporter;
use Go\Console\Warmup\WarmupReporter;
use Go\Core\AdvisorIndex;
use Go\Core\AspectKernel;
use Go\Instrument\ClassLoading\Opcache\PreloadScriptGenerator;
use Go\Instrument\ClassLoading\Warmup\FileWarmer;
use Go\Instrument\ClassLoading\Warmup\WorkerPool;
use Go\Instrument\FileSystem\Enumerator;
use Go\Instrument\PathResolver;
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
        /** Number of worker processes, 1 weaves in this process */
        protected int $workers = 1,
        /** @var list<string> Command line that starts one worker process (WarmupWorker::commandLine()), none weaves in this process */
        protected array $workerCommandLine = [],
        /** Number of parsed files kept in memory when weaving in this process, see FileWarmer */
        protected int $cachedFiles = FileWarmer::DEFAULT_CACHED_FILES,
        /** Directory to build the cache in instead of the one of the kernel, see warmUp() */
        protected ?string $cacheDir = null,
        /** Path of the opcache.preload script to write after a complete warm-up, '' for the default one in the cache directory, null for none */
        protected ?string $preloadFile = null,
    ) {}

    /**
     * Asks the warmup loop to stop cleanly before processing the next file
     */
    public function interrupt(): void
    {
        $this->interrupted = true;
    }

    /**
     * Warms up the cache
     *
     * With a cache directory of its own the warmer builds the cache there instead of the directory of the kernel,
     * from scratch: the kernel keeps serving the classes of this process from its own directory, and the files of
     * the other directory are woven again whatever it contains (the workers build it too). That is how a deployment
     * prepares the cache of the next release next to the running one: the cache files are location independent.
     *
     * The directory being built is created when it is missing, also the directory of the kernel itself: a kernel
     * with {@see \Go\Aop\Features::PREBUILT_CACHE} never creates it, the deployment does, with this warm-up.
     *
     * @return int Number of files that failed to process
     */
    public function warmUp(): int
    {
        $options = $this->aspectKernel->getOptions();

        if (empty($options['cacheDir'])) {
            throw InvalidConfigurationException::cacheWarmerWithoutCacheDir();
        }

        $cacheDir         = $this->cacheDir !== null && $this->cacheDir !== '' ? $this->cacheDir : $options['cacheDir'];
        $cachePathManager = $this->aspectKernel->getContainer()->getService(CachePathManager::class);

        // Also in place through the manager: the directory is created when the kernel did not (PREBUILT_CACHE)
        return $cachePathManager->withCacheDir($cacheDir, function () use ($cachePathManager): int {
            $errors = $this->weaveApplication();
            // Only a complete cache is worth preloading; the deployment fails on errors anyway
            if ($this->preloadFile !== null && $errors === 0 && !$this->interrupted) {
                $this->writePreloadScript($cachePathManager);
            }

            return $errors;
        });
    }

    /**
     * Writes the opcache.preload script of the cache just built, inside the directory of the build
     *
     * The manager still works on that directory here, so its class map and skipped classes are the ones of the build.
     */
    private function writePreloadScript(CachePathManager $cachePathManager): void
    {
        $cacheDir = (string) $cachePathManager->getCacheDir();
        $script   = $this->preloadFile === '' ? PreloadScriptGenerator::defaultScriptPath($cacheDir) : (string) $this->preloadFile;
        $resolved = PathResolver::realpath($script);
        $script   = is_string($resolved) ? $resolved : $script;

        $generator = new PreloadScriptGenerator(
            $cacheDir,
            $this->aspectKernel->getOptions()['appDir'],
            $cachePathManager->queryClassMap(),
            $cachePathManager->querySkippedClasses(),
            ComposerAutoloader::ofProcess()->autoloadFile,
        );
        $cachePathManager->getCacheFileWriter()->write($script, $generator->generate($script));
        $this->output->writeln(sprintf('Preload script written to <info>%s</info>.', $script));
    }

    /**
     * Weaves every file below the include paths, in this process or in the workers
     *
     * @return int Number of files that failed to process
     */
    private function weaveApplication(): int
    {
        $options = $this->aspectKernel->getOptions();

        // One walk of the application tree: the collected list is counted and then processed
        $enumerator = new Enumerator($options['appDir'], $options['includePaths'], $options['excludePaths']);
        $files      = iterator_to_array($enumerator->enumerate(), false);
        // In path order: the file system lists a directory in any order, which differs between machines.
        // Sorted, every run weaves the files in the same order, and with fail-fast stops on the same file
        usort($files, static fn(SplFileInfo $left, SplFileInfo $right): int => strcmp($left->getPathname(), $right->getPathname()));
        // No more workers than files; without a command line to start them, the files are woven here
        $workers    = $this->workerCommandLine !== [] ? max(1, min($this->workers, count($files))) : 1;

        $reporter = $this->output->isVerbose()
            ? new FileListReporter($this->output)
            : new ProgressBarReporter($this->output, $options['appDir']);
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
     * @param list<SplFileInfo>                  $files
     * @param \Closure(string, ?string): void    $onResult
     */
    private function warmUpInProcess(array $files, WarmupReporter $reporter, \Closure $onResult): void
    {
        // The transformation pipeline is registered lazily; the filter URIs used by FileWarmer bypass
        // FilterInjectorTransformer::rewrite(), so bring it up explicitly
        SourceTransformingLoader::ensureRegistered($this->aspectKernel->getContainer());

        $fileWarmer = new FileWarmer($this->cachedFiles);
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

        // Largest files first: they take longest, and the small ones left for the end even out the workers.
        // The sort is stable: files of the same size stay in path order
        // Each size is read once: the comparator would stat both files on every comparison
        $sizedFiles = array_map(
            static fn(SplFileInfo $file): array => [$file->getSize(), $file],
            $files,
        );
        usort($sizedFiles, static fn(array $left, array $right): int => $right[0] <=> $left[0]);
        $paths = array_map(static fn(array $sized): string => self::pathOf($sized[1]), $sizedFiles);

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
