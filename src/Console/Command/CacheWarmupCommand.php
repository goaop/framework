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

use Go\Instrument\ClassLoading\CacheWarmer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command for warming the cache
 */
#[AsCommand(
    name: 'cache:warmup:aop',
    description: 'Warm up the cache with woven aspects',
    help: <<<EOT
Initializes the kernel and, if successful, warm up the cache for PHP
files under the application directory.

By default, the cache directory is taken from configured AspectKernel class.
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->loadAspectKernel($input, $output);

        $this->cacheWarmer = $this->createCacheWarmer($output);
        $this->cacheWarmer->warmUp();

        if ($this->receivedSignal !== null) {
            $output->writeln('<comment>Cache warmup was interrupted by a signal.</comment>');

            return 128 + $this->receivedSignal;
        }

        return Command::SUCCESS;
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
