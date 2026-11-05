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

namespace Go\Console\Command;

use Go\Aop\Exception\InvalidConfigurationException;
use Go\Instrument\ClassLoading\CacheWarmer;
use Go\Instrument\ClassLoading\Warmup\FileWarmer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command for warming the cache
 *
 * Workers: `--workers=N` weaves in N worker processes, each of them boots the kernel with the loader again. The
 * default, 1, weaves in the command process itself.
 *
 * Cooperation: the command process coordinates the workers (see CacheWarmer and WorkerPool). It loads the aspects
 * once, so their advisor caches are written before the workers start. It hands the files out from a queue, largest
 * first, and keeps each worker two files ahead, so a worker stuck on large files simply takes fewer of them. Every
 * worker answers each file with the cache records it produced; the command merges them and writes
 * `_transformation.cache` and `_include.cache` once at the end (also when the warmup is interrupted, so the files done
 * so far stay cached), because workers writing their own copy would overwrite each other. A worker that dies (a fatal
 * error, out of memory) fails the file it was weaving, with its error output, and a new worker takes over its other
 * files. Ctrl+C or SIGTERM stops handing out files, and the workers finish the files they have.
 *
 * Progress: a progress bar on the error output (see ProgressBarReporter), redrawn in place on a terminal and written
 * as a line at every tenth of the files elsewhere; `-v` lists every file, `-q` shows nothing but the exit code.
 *
 * A command that boots the kernel in its own way (an overridden loadAspectKernel()) also overrides
 * getWorkerCommandLine() to start its own workers, or returns `[]` there to weave in one process.
 */
#[AsCommand(
    name: 'cache:warmup:aop',
    description: 'Warm up the cache with woven aspects',
    help: <<<EOT
Initializes the kernel and, if successful, warm up the cache for PHP
files under the application directory.

By default, the cache directory is taken from configured AspectKernel class.

The command fails when a file cannot be woven, so a deploy never ships an
incomplete prebuilt cache. Errors are written to stderr; use --fail-fast to
stop at the first one.

Large projects are woven faster in parallel worker processes. Every worker
boots the kernel with the loader again; the command hands the files out and
writes the cache metadata of all workers at the end:

  <info>%command.full_name% web/index.php --workers=8</info>

Each weaving process keeps the last 100 parsed files in memory; set
<info>--cached-files</info> higher to re-parse less, lower to use less memory.

The progress is shown as a progress bar; <info>-v</info> lists every file.

With <info>--cache-dir</info> the cache is built in another directory, next to the
one the kernel runs on, e.g. to prepare the cache of a deployment: the kernel and
the workers keep using their own directory, and a cache built this way is
complete and location independent.

<info>--preload</info> writes the opcache.preload script of the cache, <info>_preload.php</info> in the
cache directory or the file given as <info>--preload=FILE</info>. Point opcache.preload at
it (with opcache.preload_user) and every woven class is linked once when PHP
starts instead of being loaded by each request:

  <info>%command.full_name% web/index.php --preload</info>
EOT,
)]
class CacheWarmupCommand extends BaseAspectCommand implements SignalableCommandInterface
{
    /**
     * Signal that interrupted the warmup loop, if any
     */
    private ?int $receivedSignal = null;

    /**
     * Currently running cache warmer, used to interrupt the warmup loop on signals
     */
    private ?CacheWarmer $cacheWarmer = null;

    protected function configure(): void
    {
        parent::configure();
        $this->addOption('fail-fast', null, InputOption::VALUE_NONE, 'Stop after the first file that fails to weave');
        $this->addOption(
            'workers',
            'w',
            InputOption::VALUE_REQUIRED,
            'Number of parallel worker processes',
            '1',
        );
        $this->addOption(
            'cached-files',
            null,
            InputOption::VALUE_REQUIRED,
            'Number of parsed files each weaving process keeps in memory: more is faster, fewer uses less memory',
            (string) FileWarmer::DEFAULT_CACHED_FILES,
        );
        $this->addOption('cache-dir', null, InputOption::VALUE_REQUIRED, 'Build the cache in this directory instead of the one of the kernel');
        $this->addOption(
            'preload',
            null,
            InputOption::VALUE_OPTIONAL,
            'Write the opcache.preload script of the cache, to this file or to _preload.php in the cache directory',
            // Distinguishes the option left out (false) from the option without a value (null)
            false,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workers     = self::parsePositiveInteger($input, 'workers');
        $cachedFiles = self::parsePositiveInteger($input, 'cached-files');
        $cacheDir    = $input->getOption('cache-dir');
        if ($cacheDir !== null && !is_string($cacheDir)) {
            throw InvalidConfigurationException::cacheDirOptionNotString();
        }
        $preload = $input->getOption('preload');
        if ($preload !== false && $preload !== null && !is_string($preload)) {
            throw InvalidConfigurationException::optionNotPath('preload');
        }
        // Without a value the script goes to the cache directory being built, which the warmer knows
        $preloadFile = $preload === false ? null : ($preload ?? '');
        $this->loadAspectKernel($input, $output);

        $this->cacheWarmer = $this->createCacheWarmer($output, (bool) $input->getOption('fail-fast'), $workers, $cachedFiles, $cacheDir, $preloadFile);
        $errors            = $this->cacheWarmer->warmUp();

        if ($this->receivedSignal !== null) {
            $output->writeln('<comment>Cache warmup was interrupted by a signal.</comment>');

            return 128 + $this->receivedSignal;
        }

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Parses an option that takes a positive number
     */
    private static function parsePositiveInteger(InputInterface $input, string $option): int
    {
        $value = $input->getOption($option);
        if (!is_string($value) || !ctype_digit($value) || (int) $value < 1) {
            throw InvalidConfigurationException::optionNotPositiveInteger($option, is_string($value) ? $value : get_debug_type($value));
        }

        return (int) $value;
    }

    /**
     * @return list<int>
     */
    public function getSubscribedSignals(): array
    {
        return defined('SIGINT') ? [SIGINT, SIGTERM] : [];
    }

    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->receivedSignal = $signal;
        $this->cacheWarmer?->interrupt();

        // Let the warmup loop stop cleanly, execute() maps the signal to an exit code
        return false;
    }
}
