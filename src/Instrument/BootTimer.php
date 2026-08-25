<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument;

use function count;
use function file_put_contents;
use function get_included_files;
use function getenv;
use function hrtime;
use function json_encode;
use function memory_get_peak_usage;
use function memory_get_usage;
use function register_shutdown_function;

/**
 * Lightweight boot-time profiler used by the benchmark harness.
 *
 * Disabled unless the GO_AOP_BENCH_SPANS environment variable contains a writable
 * file path; when disabled every call is a single static bool check, so the
 * instrumentation can stay in the hot path permanently.
 */
final class BootTimer
{
    public static bool $enabled = false;

    private static string $outputFile = '';

    private static int $originNs = 0;

    /** @var list<array{n: string, s: int, d: int, l: int, f: int, m: int}> */
    private static array $spans = [];

    /** @var list<array{0: string, 1: int, 2: int, 3: int}> name, startNs, includedFiles, memory */
    private static array $stack = [];

    /** @var array<string, float|int> */
    private static array $counters = [];

    public static function initFromEnv(): void
    {
        $outputFile = getenv('GO_AOP_BENCH_SPANS');
        if (!self::$enabled && is_string($outputFile) && $outputFile !== '') {
            self::$enabled    = true;
            self::$outputFile = $outputFile;
            self::$originNs   = hrtime(true);
            register_shutdown_function(self::dump(...));
        }
    }

    public static function begin(string $name): void
    {
        if (!self::$enabled) {
            return;
        }
        self::$stack[] = [$name, hrtime(true), count(get_included_files()), memory_get_usage()];
    }

    public static function end(): void
    {
        if (!self::$enabled) {
            return;
        }
        $frame = array_pop(self::$stack);
        if ($frame === null) {
            return;
        }
        [$name, $startNs, $files, $memory] = $frame;
        self::$spans[] = [
            'n' => $name,
            's' => $startNs - self::$originNs,
            'd' => hrtime(true) - $startNs,
            'l' => count(self::$stack),
            'f' => count(get_included_files()) - $files,
            'm' => memory_get_usage() - $memory,
        ];
    }

    /**
     * Increments an aggregate counter (used on per-class code paths where
     * individual spans would be too noisy).
     */
    public static function add(string $counter, float|int $value = 1): void
    {
        if (!self::$enabled) {
            return;
        }
        self::$counters[$counter] = (self::$counters[$counter] ?? 0) + $value;
    }

    public static function dump(): void
    {
        if (!self::$enabled) {
            return;
        }
        $report = [
            'php'      => PHP_VERSION,
            'spans'    => self::$spans,
            'counters' => self::$counters,
            'files'    => count(get_included_files()),
            'peakMem'  => memory_get_peak_usage(),
            'totalNs'  => hrtime(true) - self::$originNs,
        ];
        file_put_contents(self::$outputFile, json_encode($report, JSON_PRETTY_PRINT));
    }
}
