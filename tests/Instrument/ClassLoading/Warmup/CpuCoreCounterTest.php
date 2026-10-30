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

use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\TestCase;

final class CpuCoreCounterTest extends TestCase
{
    public function testCountsAtLeastOneCore(): void
    {
        $this->assertGreaterThanOrEqual(1, CpuCoreCounter::count());
    }

    #[RequiresOperatingSystemFamily('Linux')]
    public function testLinuxCountIsLimitedByTheAffinityOfTheProcess(): void
    {
        $status = (string) file_get_contents('/proc/self/status');
        if (preg_match('/^Cpus_allowed_list:\s*(\S+)/m', $status) !== 1) {
            $this->markTestSkipped('No affinity list in /proc/self/status');
        }

        $this->assertLessThanOrEqual(substr_count((string) file_get_contents('/proc/cpuinfo'), "\nprocessor") + 1, CpuCoreCounter::count('Linux'));
    }

    public function testWindowsCountComesFromTheEnvironment(): void
    {
        $previous = getenv('NUMBER_OF_PROCESSORS');
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows keeps its own value of the variable, putenv() does not replace it for getenv()
            $this->assertSame(max(1, (int) $previous), CpuCoreCounter::count('Windows'));

            return;
        }
        try {
            putenv('NUMBER_OF_PROCESSORS=6');
            $this->assertSame(6, CpuCoreCounter::count('Windows'));
            putenv('NUMBER_OF_PROCESSORS=many');
            $this->assertSame(1, CpuCoreCounter::count('Windows'));
        } finally {
            putenv($previous === false ? 'NUMBER_OF_PROCESSORS' : 'NUMBER_OF_PROCESSORS=' . $previous);
        }
    }

    public function testOtherSystemsAskSysctl(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('The sysctl command line redirects to /dev/null, a path cmd.exe does not know');
        }
        // sysctl answers hw.ncpu on macOS and the BSDs only: anything else falls back to one core
        $this->assertGreaterThanOrEqual(1, CpuCoreCounter::count('Darwin'));
    }
}
