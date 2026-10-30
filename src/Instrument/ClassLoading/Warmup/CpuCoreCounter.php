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

namespace Go\Instrument\ClassLoading\Warmup;

/**
 * Counts the CPU cores this process may use, for the default number of cache warmup workers
 *
 * @internal
 */
final class CpuCoreCounter
{
    /**
     * Returns the number of usable CPU cores, at least 1
     *
     * On Linux the CPU affinity of the process and the CPU quota of its cgroup (containers) are honoured.
     *
     * @param string $osFamily Family of the operating system, PHP_OS_FAMILY
     */
    public static function count(string $osFamily = PHP_OS_FAMILY): int
    {
        $count = match ($osFamily) {
            'Windows' => self::fromWindowsEnvironment(),
            'Linux'   => self::fromLinuxAffinity() ?? self::fromLinuxCpuInfo(),
            default   => self::fromSysctl(),
        };
        $quota = $osFamily === 'Linux' ? self::fromCgroupQuota() : null;
        if ($quota !== null) {
            $count = $count === null ? $quota : min($count, $quota);
        }

        return max(1, $count ?? 1);
    }

    private static function fromWindowsEnvironment(): ?int
    {
        $processors = getenv('NUMBER_OF_PROCESSORS');

        return is_string($processors) && ctype_digit($processors) ? (int) $processors : null;
    }

    /**
     * Counts the CPUs of the affinity mask of the process, e.g. `Cpus_allowed_list:	0-3,8`
     */
    private static function fromLinuxAffinity(): ?int
    {
        $status = @file_get_contents('/proc/self/status');
        if ($status === false || preg_match('/^Cpus_allowed_list:\s*(\S+)/m', $status, $matches) !== 1) {
            return null;
        }
        $count = 0;
        foreach (explode(',', $matches[1]) as $range) {
            $bounds = explode('-', $range);
            $count += count($bounds) === 2 ? (int) $bounds[1] - (int) $bounds[0] + 1 : 1;
        }

        return $count > 0 ? $count : null;
    }

    private static function fromLinuxCpuInfo(): ?int
    {
        $cpuInfo = @file_get_contents('/proc/cpuinfo');
        if ($cpuInfo === false) {
            return null;
        }
        $count = preg_match_all('/^processor\s*:/m', $cpuInfo);

        return $count > 0 ? $count : null;
    }

    /**
     * Reads the CPU quota of a cgroup v2, e.g. `200000 100000` for two CPUs or `max 100000` for no quota
     */
    private static function fromCgroupQuota(): ?int
    {
        $cpuMax = @file_get_contents('/sys/fs/cgroup/cpu.max');
        if ($cpuMax === false || preg_match('/^(\d+)\s+(\d+)/', $cpuMax, $matches) !== 1 || (int) $matches[2] === 0) {
            return null;
        }

        return max(1, (int) ceil((int) $matches[1] / (int) $matches[2]));
    }

    /**
     * Asks sysctl on macOS and the BSDs
     */
    private static function fromSysctl(): ?int
    {
        if (!function_exists('shell_exec')) {
            return null;
        }
        $count = @shell_exec('sysctl -n hw.ncpu 2>/dev/null');

        return is_string($count) && ctype_digit(trim($count)) ? (int) trim($count) : null;
    }
}
