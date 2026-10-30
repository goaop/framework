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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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

The command fails when a file cannot be woven, so a deploy never ships an
incomplete prebuilt cache. Errors are written to stderr; use --fail-fast to
stop at the first one.

Files are woven in parallel worker processes, one per CPU core by default
(<info>auto</info>, only for projects large enough to gain from it). Every worker boots
the kernel with the loader again; the command hands the files out and writes
the cache metadata of all workers at the end:

  <info>%command.full_name% web/index.php --workers=8</info>
  <info>%command.full_name% web/index.php --workers=1</info>   weaves in this process only

On an interactive terminal the progress is a progress bar, in a CI log or a
file a progress line at every tenth of the files; <info>-v</info> lists every file.
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
            'Number of parallel worker processes, or "auto" for one per CPU core',
            'auto',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workers = self::parseWorkers($input->getOption('workers'));
        $this->loadAspectKernel($input, $output);

        $this->cacheWarmer = $this->createCacheWarmer($output, (bool) $input->getOption('fail-fast'), $workers);
        $errors            = $this->cacheWarmer->warmUp();

        if ($this->receivedSignal !== null) {
            $output->writeln('<comment>Cache warmup was interrupted by a signal.</comment>');

            return 128 + $this->receivedSignal;
        }

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Parses the --workers option: a positive number, or "auto" for one worker per CPU core
     */
    private static function parseWorkers(mixed $workers): int
    {
        if ($workers === 'auto') {
            return CacheWarmer::AUTO_WORKERS;
        }
        if (!is_string($workers) || !ctype_digit($workers) || (int) $workers < 1) {
            throw InvalidConfigurationException::invalidWorkerCount(is_string($workers) ? $workers : get_debug_type($workers));
        }

        return (int) $workers;
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
