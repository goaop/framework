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

use Symfony\Component\Console\Helper\Helper;

/**
 * Writes a progress line at every tenth of the files, or every ten seconds: for logs and outputs that are not a
 * terminal, where a redrawn progress bar would leave every redraw behind
 *
 * @internal
 */
final class MilestoneReporter extends AbstractWarmupReporter
{
    /**
     * Number of progress lines for the whole warmup
     */
    private const int MILESTONES = 10;

    /**
     * Longest time without a progress line, in seconds
     */
    private const float MAX_SILENCE = 10.0;

    private int $nextMilestone = 0;

    private float $lastLineTime = 0.0;

    public function start(int $total, int $workers): void
    {
        parent::start($total, $workers);
        $this->nextMilestone = $this->milestoneAfter(0);
        $this->lastLineTime  = $this->elapsedSeconds();
    }

    public function advance(string $file, ?string $error): void
    {
        parent::advance($file, $error);

        if ($error !== null) {
            $this->writeError($file, $error);
        }

        $elapsed = $this->elapsedSeconds();
        if ($this->processed >= $this->nextMilestone || $elapsed - $this->lastLineTime >= self::MAX_SILENCE) {
            $this->nextMilestone = $this->milestoneAfter($this->processed);
            $this->lastLineTime  = $elapsed;
            $this->writeProgressLine();
        }
    }

    private function writeProgressLine(): void
    {
        $width   = strlen((string) $this->total);
        $percent = $this->total > 0 ? intdiv($this->processed * 100, $this->total) : 100;
        $rate    = $this->rate();
        $left    = $rate > 0 ? ($this->total - $this->processed) / $rate : 0.0;

        $this->progressOutput->writeln(sprintf(
            ' %*d/%d [%3d%%] %s elapsed, %s left, %.1f files/s%s',
            $width,
            $this->processed,
            $this->total,
            $percent,
            Helper::formatTime($this->elapsedSeconds()),
            Helper::formatTime($left),
            $rate,
            $this->errors > 0 ? sprintf(', <fg=red>%d errors</>', $this->errors) : '',
        ));
    }

    /**
     * Returns the number of processed files of the milestone after the given one
     */
    private function milestoneAfter(int $processed): int
    {
        $step = max(1, intdiv($this->total + self::MILESTONES - 1, self::MILESTONES));

        return min($this->total, (intdiv($processed, $step) + 1) * $step);
    }
}
