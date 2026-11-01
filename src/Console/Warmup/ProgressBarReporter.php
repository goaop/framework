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

use Go\Instrument\PathResolver;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

/**
 * Shows the progress as a progress bar
 *
 * On a decorated output (a terminal) the bar is redrawn in place, with the file at hand below it, and errors are
 * written above it. On any other output (a CI log, a file) the bar is written as a new line at every tenth of the files.
 *
 * @internal
 */
final class ProgressBarReporter extends AbstractWarmupReporter
{
    private const string FORMAT = ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%%errors%';

    private ?ProgressBar $progressBar = null;

    public function __construct(
        OutputInterface $output,
        /** Directory the shown files are relative to, usually the application directory */
        private readonly ?string $baseDir = null,
    ) {
        parent::__construct($output);
    }

    public function start(int $total, int $workers): void
    {
        parent::start($total, $workers);

        $progressBar = new ProgressBar($this->progressOutput, $total);
        if ($this->progressOutput->isDecorated()) {
            $progressBar->setFormat(self::FORMAT . "\n %message%");
            $progressBar->setRedrawFrequency(1);
        } else {
            // A line at every tenth of the files only, also when they are woven fast
            $progressBar->setFormat(self::FORMAT);
            $progressBar->minSecondsBetweenRedraws(0);
            $progressBar->maxSecondsBetweenRedraws(10);
        }
        $progressBar->setMessage('');
        $progressBar->setMessage('', 'errors');
        $progressBar->start();
        $this->progressBar = $progressBar;
    }

    public function advance(string $file, ?string $error): void
    {
        parent::advance($file, $error);
        $progressBar = $this->progressBar;
        if ($progressBar !== null) {
            if ($error !== null) {
                $progressBar->clear();
                $this->writeError($file, $error);
                $progressBar->setMessage(sprintf(', <fg=red>%d errors</>', $this->errors), 'errors');
            }
            $progressBar->setMessage($this->shortenPath($file));
            $progressBar->advance();
            if ($error !== null && $this->progressOutput->isDecorated()) {
                // The cleared bar is back below the error at once, even when the advance skipped a redraw
                $progressBar->display();
            }
        }
    }

    public function interrupt(): void
    {
        $this->progressBar?->clear();
        parent::interrupt();
    }

    public function finish(int $processed, int $errors): void
    {
        if ($this->progressBar !== null && $processed === $this->total) {
            // The line of the file at hand is gone, only the bar stays
            $this->progressBar->setFormat(self::FORMAT);
            $this->progressBar->finish();
            $this->progressOutput->writeln('');
        } else {
            // An unfinished bar is gone, the summary tells how far the warmup got
            $this->progressBar?->clear();
        }
        $this->progressBar = null;

        parent::finish($processed, $errors);
    }

    /**
     * Shows the file relative to the base directory, cut from the left to fit on its line: a wrapped line would
     * break the redraw of the bar
     */
    private function shortenPath(string $file): string
    {
        if ($this->baseDir !== null) {
            $file = ltrim(PathResolver::rebase($file, $this->baseDir, '') ?? $file, '/\\');
        }
        $maxWidth = max(10, new Terminal()->getWidth() - 2);

        return Helper::width($file) > $maxWidth ? '...' . Helper::substr($file, 3 - $maxWidth) : $file;
    }
}
