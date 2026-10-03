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
use Go\Core\AspectKernel;
use Go\Instrument\ClassLoading\CacheWarmer;
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
            throw new InvalidConfigurationException('Loader argument must be a string');
        }
        $path = stream_resolve_include_path($loader);
        if ($path === false || !is_readable($path)) {
            throw new InvalidConfigurationException("Invalid loader path: {$loader}");
        }

        // The loader is executed only to boot the kernel: anything it prints is discarded
        ob_start();
        try {
            include_once $path;
        } finally {
            ob_end_clean();
        }

        if (!class_exists(AspectKernel::class, false)) {
            $message = "Kernel was not initialized yet, please configure it in the {$path}";
            throw new InvalidConfigurationException($message);
        }

        $this->aspectKernel = AspectKernel::getInstance();
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
     */
    protected function createCacheWarmer(OutputInterface $output, bool $failFast = false): CacheWarmer
    {
        return new CacheWarmer($this->aspectKernel, $output, $failFast);
    }
}
