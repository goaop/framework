<?php

declare(strict_types=1);
/*
 * Go! AOP framework — boot-time benchmark orchestrator.
 *
 * Runs bench-request.php in child PHP processes across a matrix of scenarios
 * and prints/collects median timings with per-span breakdowns.
 *
 * Usage:
 *   php tests/Benchmark/run-bench.php [--php=php8.5] [--profile=production] \
 *       [--mode=warm] [--runs=15] [--out=/tmp/bench-results]
 *
 * Modes:
 *   cold  clear aspect cache + opcache file cache, single timed run (weaving happens inline)
 *   warm  warm the caches (aspect warmup + 2 priming runs), then N timed runs
 *
 * CLI opcache does not survive between processes, so an opcache file cache is
 * used to emulate the persistent opcache of a real FPM/server deployment.
 */

error_reporting(E_ALL);

$options = getopt('', ['php::', 'profile::', 'mode::', 'runs::', 'out::', 'label::']);
$php     = $options['php'] ?? 'php';
$profile = $options['profile'] ?? 'production';
$mode    = $options['mode'] ?? 'warm';
$runs    = (int) ($options['runs'] ?? 15);
$outDir  = rtrim($options['out'] ?? (sys_get_temp_dir() . '/goaop-bench'), '/');
$label   = $options['label'] ?? sprintf('%s-%s-%s', basename((string) $php), $profile, $mode);

$projectDir      = __DIR__ . '/../Fixtures/project';
$benchScript     = __DIR__ . '/bench-request.php';
$aspectCacheDirs = [
    $projectDir . '/var/cache/aspect',
    $projectDir . '/var/cache/aspect-prod',
];
$opcacheFileCache = $outDir . '/opcache-' . md5((string) $php . $profile);

@mkdir($outDir, 0777, true);
@mkdir($opcacheFileCache, 0777, true);

/** @param list<string> $extraEnv */
function runRequest(string $php, string $benchScript, string $profile, string $spanFile, string $opcacheFileCache, bool $opcacheEnabled = true): array
{
    $iniOptions = $opcacheEnabled
        ? sprintf(
            '-d opcache.enable_cli=1 -d opcache.enable=1 -d opcache.file_cache=%s -d opcache.validate_timestamps=1',
            escapeshellarg($opcacheFileCache)
        )
        : '-d opcache.enable_cli=0';

    $cmd = sprintf(
        'GO_AOP_CONFIGURATION=%s GO_AOP_BENCH_SPANS=%s %s %s %s 2>&1',
        escapeshellarg($profile),
        escapeshellarg($spanFile),
        escapeshellarg($php),
        $iniOptions,
        escapeshellarg($benchScript)
    );

    $startNs = hrtime(true);
    exec($cmd, $output, $exitCode);
    $wallNs = hrtime(true) - $startNs;

    if ($exitCode !== 0) {
        fwrite(STDERR, "Benchmark request failed (exit $exitCode):\n" . implode("\n", $output) . "\n");
        exit(1);
    }

    $report = is_file($spanFile) ? json_decode((string) file_get_contents($spanFile), true) : null;

    return ['wallNs' => $wallNs, 'report' => $report];
}

function clearDirs(array $dirs): void
{
    foreach ($dirs as $dir) {
        if (is_dir($dir)) {
            exec('rm -rf ' . escapeshellarg($dir . '/') . '*');
        }
    }
}

/** @param list<float> $values */
function percentile(array $values, float $p): float
{
    sort($values);
    $index = (int) floor((count($values) - 1) * $p);

    return $values[$index];
}

$spanFile = $outDir . '/' . $label . '-spans.json';
$samples  = [];

if ($mode === 'cold') {
    clearDirs($aspectCacheDirs);
    clearDirs([$opcacheFileCache]);
    $samples[] = runRequest($php, $benchScript, $profile, $spanFile, $opcacheFileCache);
} else {
    // Warm everything: aspect cache via warmup command + priming requests
    // (priming also writes the _aspect advisor cache and fills the opcache file cache).
    $console = $projectDir . '/bin/console';
    exec(sprintf(
        'GO_AOP_CONFIGURATION=%s %s %s cache:warmup:aop %s 2>&1',
        escapeshellarg($profile),
        escapeshellarg((string) $php),
        escapeshellarg($console),
        escapeshellarg($projectDir . '/web/index.php')
    ));
    runRequest($php, $benchScript, $profile, $spanFile, $opcacheFileCache);
    runRequest($php, $benchScript, $profile, $spanFile, $opcacheFileCache);

    for ($i = 0; $i < $runs; $i++) {
        $samples[] = runRequest($php, $benchScript, $profile, $spanFile, $opcacheFileCache);
    }
}

// Aggregate: median by total in-process time, keep that run's span breakdown.
usort($samples, fn(array $a, array $b): int => ($a['report']['totalNs'] ?? PHP_INT_MAX) <=> ($b['report']['totalNs'] ?? PHP_INT_MAX));
$median = $samples[intdiv(count($samples), 2)];

$totals = array_map(fn(array $sample): float => (float) ($sample['report']['totalNs'] ?? 0), $samples);
$walls  = array_map(fn(array $sample): float => (float) $sample['wallNs'], $samples);

$result = [
    'label'      => $label,
    'php'        => $median['report']['php'] ?? 'unknown',
    'profile'    => $profile,
    'mode'       => $mode,
    'runs'       => count($samples),
    'totalMs'    => ['median' => percentile($totals, 0.5) / 1e6, 'p10' => percentile($totals, 0.1) / 1e6, 'p90' => percentile($totals, 0.9) / 1e6],
    'wallMs'     => ['median' => percentile($walls, 0.5) / 1e6, 'p10' => percentile($walls, 0.1) / 1e6, 'p90' => percentile($walls, 0.9) / 1e6],
    'medianRun'  => $median['report'],
];

file_put_contents($outDir . '/' . $label . '.json', json_encode($result, JSON_PRETTY_PRINT));

printf(
    "%-28s total(median) %8.2f ms   p10 %8.2f   p90 %8.2f   (process wall median %8.2f ms)\n",
    $label,
    $result['totalMs']['median'],
    $result['totalMs']['p10'],
    $result['totalMs']['p90'],
    $result['wallMs']['median']
);
