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
use Go\Console\KernelLoader;
use Go\Core\AspectKernel;
use Go\Instrument\ClassLoading\CacheWarmer;
use Go\Instrument\ClassLoading\Warmup\WarmupWorker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base command for all aspect commands
 */
abstract class BaseAspectCommand extends Command
{
    /**
     * Stores an instance of aspect kernel
     */
    protected AspectKernel $aspectKernel;

    /**
     * Absolute path of the loader file that booted the kernel, null until loadAspectKernel() ran
     */
    protected ?string $loaderPath = null;

    protected function configure(): void
    {
        $this->addArgument(
            'loader',
            InputArgument::REQUIRED,
            'Path to the PHP file that initializes your aspect kernel (usually the front controller or a bootstrap '
            . 'file). The file is executed, so it must not dispatch a request or produce side effects',
        );
    }

    /**
     * Loads aspect kernel.
     *
     * Aspect kernel is loaded by executing loader and fetching singleton instance.
     * If your application environment initializes aspect kernel differently, you may
     * modify this method to get aspect kernel suitable to your needs.
     */
    protected function loadAspectKernel(InputInterface $input, OutputInterface $output): void
    {
        $loader = $input->getArgument('loader');
        if (!is_string($loader)) {
            throw InvalidConfigurationException::loaderArgumentNotString();
        }
        $this->loaderPath   = KernelLoader::resolve($loader);
        $this->aspectKernel = KernelLoader::load($this->loaderPath);
    }

    /**
     * Returns a " Did you mean ...?" hint with the candidate closest to the given name, or an empty string
     *
     * @param iterable<string> $candidates
     */
    protected static function suggestAlternative(string $given, iterable $candidates): string
    {
        $bestCandidate = null;
        $bestDistance  = PHP_INT_MAX;
        foreach ($candidates as $candidate) {
            $distance = levenshtein(strtolower($given), strtolower($candidate));
            if ($distance < $bestDistance) {
                [$bestCandidate, $bestDistance] = [$candidate, $distance];
            }
        }
        // Only close matches are worth suggesting: at most a third of the name may differ
        if ($bestCandidate === null || $bestDistance > max(3, intdiv(strlen($given), 3))) {
            return '';
        }

        return sprintf(' Did you mean "%s"?', $bestCandidate);
    }

    /**
     * Creates the cache warmer for the loaded aspect kernel
     *
     * @param bool $failFast Whether the warmer stops after the first file that fails to process
     * @param int  $workers  Number of worker processes, CacheWarmer::AUTO_WORKERS for one per CPU core
     */
    protected function createCacheWarmer(OutputInterface $output, bool $failFast = false, int $workers = 1): CacheWarmer
    {
        $workerCommandLine = $workers !== 1 ? $this->getWorkerCommandLine() : [];

        return new CacheWarmer($this->aspectKernel, $output, $failFast, $workers, $workerCommandLine);
    }

    /**
     * Returns the command line that starts one cache warmup worker process
     *
     * A worker boots the kernel with the loader file, like loadAspectKernel() does. A command that boots the kernel
     * differently returns the command line of its own worker here, or an empty list to weave in this process only.
     *
     * @return list<string>
     */
    protected function getWorkerCommandLine(): array
    {
        return $this->loaderPath !== null ? WarmupWorker::commandLine($this->loaderPath) : [];
    }
}
