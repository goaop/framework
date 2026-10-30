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

/**
 * Redraws a progress bar in place, with the file at hand below it: for an interactive terminal
 *
 * Errors are written above the bar, which is drawn again below them.
 *
 * @internal
 */
final class ProgressBarReporter extends AbstractWarmupReporter
{
    /**
     * Line of the bar and line of the file at hand
     */
    private const string FORMAT = " %current%/%max% [<fg=green>%bar%</>] %percent:3s%%  %timing%  %rate%%errors%\n %file%";

    /**
     * Line of the finished bar
     */
    private const string FINISHED_FORMAT = ' %current%/%max% [<fg=green>%bar%</>] %percent:3s%%  %timing%  %rate%%errors%';

    private ?ProgressBar $progressBar = null;

    public function __construct(
        OutputInterface $output,
        /** What the terminal can render */
        private readonly TerminalCapabilities $terminal,
        /** Directory the shown files are relative to, usually the application directory */
        private readonly ?string $baseDir = null,
        /** Shortest time between two redraws, in seconds: a fast warmup must not spend its time on drawing */
        private readonly float $minSecondsBetweenRedraws = 0.1,
    ) {
        parent::__construct($output);
    }

    public function start(int $total, int $workers): void
    {
        parent::start($total, $workers);

        // Every file is drawn, unless the previous redraw is too recent
        $progressBar = new ProgressBar($this->progressOutput, $total, $this->minSecondsBetweenRedraws);
        $progressBar->setRedrawFrequency(1);
        $progressBar->setFormat(self::FORMAT);
        $progressBar->setBarWidth(max(10, min(40, $this->terminal->width - 60)));
        if ($this->terminal->unicode) {
            $progressBar->setBarCharacter('█');
            $progressBar->setProgressCharacter('');
            $progressBar->setEmptyBarCharacter('░');
        } else {
            $progressBar->setBarCharacter('=');
            $progressBar->setProgressCharacter('>');
            $progressBar->setEmptyBarCharacter('-');
        }
        $this->progressBar = $progressBar;
        $this->updateMessages('');
        $progressBar->start();
    }

    public function advance(string $file, ?string $error): void
    {
        parent::advance($file, $error);
        $progressBar = $this->progressBar;
        if ($progressBar === null) {
            return;
        }

        if ($error !== null) {
            $progressBar->clear();
            $this->writeError($file, $error);
        }
        $this->updateMessages($file);
        $progressBar->advance();
        if ($error !== null) {
            // The bar is back below the error at once, even when the advance skipped a redraw
            $progressBar->display();
        }
    }

    public function interrupt(): void
    {
        $this->progressBar?->clear();
        parent::interrupt();
        $this->progressBar?->display();
    }

    public function finish(int $processed, int $errors): void
    {
        $progressBar = $this->progressBar;
        if ($progressBar !== null && $processed === $this->total) {
            $this->updateMessages('', finished: true);
            // The line of the file at hand is gone, only the bar stays
            $progressBar->setFormat(self::FINISHED_FORMAT);
            $progressBar->finish();
            $this->progressOutput->writeln('');
        } else {
            // An unfinished bar is gone, the summary tells how far the warmup got
            $progressBar?->clear();
        }
        $this->progressBar = null;

        parent::finish($processed, $errors);
    }

    private function updateMessages(string $file, bool $finished = false): void
    {
        if ($this->progressBar === null) {
            return;
        }
        // The timing of the bar itself counts whole seconds only
        $elapsed = $this->elapsedSeconds();
        $rate    = $this->rate();
        $timing  = Helper::formatTime($elapsed);
        if (!$finished && $this->processed > 0 && $this->processed < $this->total && $rate > 0) {
            $timing .= sprintf(', ~%s left', Helper::formatTime(($this->total - $this->processed) / $rate));
        }
        $this->progressBar->setMessage($timing, 'timing');
        $this->progressBar->setMessage(sprintf('%.1f files/s', $rate), 'rate');
        $this->progressBar->setMessage($this->errors > 0 ? sprintf('  <fg=red>%d errors</>', $this->errors) : '', 'errors');
        $this->progressBar->setMessage($this->shortenPath($file), 'file');
    }

    /**
     * Shows the file relative to the base directory, cut from the left to fit on its line
     */
    private function shortenPath(string $file): string
    {
        if ($this->baseDir !== null) {
            $file = PathResolver::rebase($file, $this->baseDir, '') ?? $file;
            $file = ltrim($file, '/\\');
        }
        $maxWidth = max(10, $this->terminal->width - 2);
        if (Helper::width($file) > $maxWidth) {
            $ellipsis = $this->terminal->unicode ? '…' : '...';
            $file     = $ellipsis . Helper::substr($file, -($maxWidth - Helper::width($ellipsis)));
        }

        return $file;
    }
}
