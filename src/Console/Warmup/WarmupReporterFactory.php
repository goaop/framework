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

use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * Picks the reporter that suits the output and the terminal behind it
 *
 * - verbose (`-v`): every file is listed, whatever the terminal is
 * - interactive terminal: a progress bar redrawn in place, Unicode characters when the terminal renders them
 * - anything else (CI log, file, pipe, TERM=dumb): a progress line at every tenth of the files
 *
 * The progress is written to the error output, so that is the stream the terminal check looks at.
 *
 * @internal
 */
final class WarmupReporterFactory
{
    /**
     * @param string|null $baseDir Directory the files in the progress bar are shown relative to
     */
    public static function create(OutputInterface $output, ?string $baseDir = null): WarmupReporter
    {
        if ($output->isVerbose()) {
            return new FileListReporter($output);
        }

        $progressOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        if ($progressOutput instanceof StreamOutput && !$output->isQuiet()) {
            $terminal = TerminalCapabilities::detect($progressOutput);
            if ($terminal->interactive) {
                return new ProgressBarReporter($output, $terminal, $baseDir);
            }
        }

        return new MilestoneReporter($output);
    }
}
