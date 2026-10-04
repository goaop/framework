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

/**
 * Compares two reports of the transformation performance group and fails when any case got slower
 *
 * Usage: php tests/Performance/compare-transformation-performance.php <base report> <head report> [max slowdown]
 *
 * A report is written by TransformationPerformanceTest when GO_AOP_TRANSFORMATION_REPORT points to a file; it may
 * hold several runs, the median ratio of each case is compared. The ratio (transformation time / plain walk time)
 * is used instead of the absolute time, so a change of the machine speed between the runs does not count. The
 * maximum slowdown defaults to 0.10: the head ratio may be at most 10% above the base ratio.
 */

/**
 * Returns the median ratio per case of a report
 *
 * @return array<string, float>
 */
$readReport = static function (string $fileName): array {
    $lines = file($fileName, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        fwrite(STDERR, "Can not read the report {$fileName}\n");
        exit(2);
    }
    $ratios = [];
    foreach ($lines as $line) {
        $entry = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($entry) || !is_string($entry['case'] ?? null) || !is_numeric($entry['ratio'] ?? null)) {
            fwrite(STDERR, "Invalid line in the report {$fileName}: {$line}\n");
            exit(2);
        }
        $ratios[$entry['case']][] = (float) $entry['ratio'];
    }
    $medians = [];
    foreach ($ratios as $case => $caseRatios) {
        sort($caseRatios);
        $medians[$case] = $caseRatios[intdiv(count($caseRatios), 2)];
    }

    return $medians;
};

$arguments = array_values(array_filter((array) ($_SERVER['argv'] ?? []), is_string(...)));
if (count($arguments) < 3) {
    fwrite(STDERR, "Usage: php compare-transformation-performance.php <base report> <head report> [max slowdown, default 0.10]\n");
    exit(2);
}
$baseRatios  = $readReport($arguments[1]);
$headRatios  = $readReport($arguments[2]);
$maxSlowdown = isset($arguments[3]) ? (float) $arguments[3] : 0.10;

$isSlower = false;
printf("%-56s %8s %8s %9s\n", 'Case', 'Base', 'Head', 'Change');
foreach ($headRatios as $case => $headRatio) {
    if (!isset($baseRatios[$case])) {
        printf("%-56s %8s %8.3f %9s\n", $case, '-', $headRatio, 'new');
        continue;
    }
    $change = $headRatio / $baseRatios[$case] - 1;
    $status = '';
    if ($change > $maxSlowdown) {
        $isSlower = true;
        $status   = ' SLOWER';
    }
    printf("%-56s %8.3f %8.3f %+8.1f%%%s\n", $case, $baseRatios[$case], $headRatio, $change * 100, $status);
}

if ($isSlower) {
    printf("\nThe transformation got more than %.0f%% slower, performance may only stay the same or improve.\n", $maxSlowdown * 100);
    exit(1);
}
printf("\nNo case got more than %.0f%% slower.\n", $maxSlowdown * 100);
