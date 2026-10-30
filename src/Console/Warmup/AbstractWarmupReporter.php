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

namespace Go\Console\Warmup;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Common part of the reporters: the header, the errors and the summary
 *
 * The header and the summary go to the standard output, errors and the progress to the error output, so the
 * standard output of a deploy script stays short.
 *
 * @internal
 */
abstract class AbstractWarmupReporter implements WarmupReporter
{
    /**
     * Output for the progress and the errors: the error output of a console output
     */
    protected readonly OutputInterface $progressOutput;

    protected int $total = 0;

    protected int $processed = 0;

    protected int $errors = 0;

    private float $startTime = 0.0;

    private int $workers = 1;

    public function __construct(
        /** Output for the header and the summary */
        protected readonly OutputInterface $output,
    ) {
        $this->progressOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
    }

    public function start(int $total, int $workers): void
    {
        $this->total     = $total;
        $this->workers   = $workers;
        $this->startTime = microtime(true);

        $this->output->writeln(sprintf('Total <info>%d</info> files to process.', $total));
        if ($workers > 1) {
            $this->output->writeln(sprintf('Weaving in <info>%d</info> parallel worker processes.', $workers));
        }
        $this->output->writeln('');
    }

    public function advance(string $file, ?string $error): void
    {
        $this->processed++;
        if ($error !== null) {
            $this->errors++;
        }
    }

    public function interrupt(): void
    {
        $this->progressOutput->writeln('<comment>[STOP]: Warmup was interrupted, stopping...</comment>');
    }

    public function finish(int $processed, int $errors): void
    {
        $this->output->writeln('');
        $this->output->writeln(sprintf(
            '<fg=green>[DONE]</>: Total processed %d, %d errors in %s%s.',
            $processed,
            $errors,
            Helper::formatTime($this->elapsedSeconds(), 2),
            $this->workers > 1 ? sprintf(' with %d workers', $this->workers) : '',
        ));
    }

    /**
     * Writes the error of a file
     */
    protected function writeError(string $file, string $error): void
    {
        $this->progressOutput->writeln(sprintf(
            '<fg=white;bg=red;options=bold>[ERR]</>: %s: %s',
            $file,
            OutputFormatter::escape($error),
        ));
    }

    protected function elapsedSeconds(): float
    {
        return microtime(true) - $this->startTime;
    }

    /**
     * Returns the number of files processed per second
     */
    protected function rate(): float
    {
        $elapsed = $this->elapsedSeconds();

        return $elapsed > 0 ? $this->processed / $elapsed : 0.0;
    }
}
