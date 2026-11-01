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
 * Shows the progress of a cache warmup
 *
 * @internal Used by CacheWarmer
 */
interface WarmupReporter
{
    /**
     * Called once, before the first file
     *
     * @param int $total   Number of files to weave
     * @param int $workers Number of worker processes, 1 when the files are woven in this process
     */
    public function start(int $total, int $workers): void;

    /**
     * Called for every processed file
     *
     * @param string|null $error Error message, null when the file was woven
     */
    public function advance(string $file, ?string $error): void;

    /**
     * Called once when the warmup stops before all files were processed (a signal, or --fail-fast)
     */
    public function interrupt(): void;

    /**
     * Called once, at the end
     */
    public function finish(int $processed, int $errors): void;
}
