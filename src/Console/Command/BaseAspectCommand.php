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
        $this->addArgument('loader', InputArgument::REQUIRED, 'Path to the aspect loader file');
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

        ob_start();
        include_once $path;
        ob_clean();

        if (!class_exists(AspectKernel::class, false)) {
            $message = "Kernel was not initialized yet, please configure it in the {$path}";
            throw new InvalidConfigurationException($message);
        }

        $this->aspectKernel = AspectKernel::getInstance();
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
