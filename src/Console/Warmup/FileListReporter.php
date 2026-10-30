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

/**
 * Lists every processed file: the verbose mode
 *
 * @internal
 */
final class FileListReporter extends AbstractWarmupReporter
{
    public function advance(string $file, ?string $error): void
    {
        parent::advance($file, $error);

        if ($error !== null) {
            $this->writeError($file, $error);

            return;
        }
        $this->output->writeln(sprintf(
            '<fg=green;options=bold>[OK]</>: <comment>%s</comment>',
            $file,
        ));
    }
}
