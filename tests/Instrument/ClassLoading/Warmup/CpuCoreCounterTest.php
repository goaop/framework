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

use Go\VirtualFileSystem\FileSystem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CpuCoreCounterTest extends TestCase
{
    private FileSystem $fileSystem;

    protected function setUp(): void
    {
        $this->fileSystem = FileSystem::mount('cpus');
        mkdir($this->fileSystem->path('/proc/self'), 0777, true);
        mkdir($this->fileSystem->path('/sys/fs/cgroup'), 0777, true);
    }

    protected function tearDown(): void
    {
        $this->fileSystem->unmount();
    }

    public function testCountsAtLeastOneCore(): void
    {
        $this->assertGreaterThanOrEqual(1, CpuCoreCounter::count());
    }

    /**
     * @return iterable<string, array{array<string, string>, int}>
     */
    public static function linuxSystems(): iterable
    {
        $cpuInfo = "processor\t: 0\nmodel name\t: CPU\n\nprocessor\t: 1\n\nprocessor\t: 2\n\nprocessor\t: 3\n";

        yield 'affinity ranges'            => [['/proc/self/status' => "Name:\tphp\nCpus_allowed_list:\t0-3,8,10-11\n", '/proc/cpuinfo' => $cpuInfo], 7];
        yield 'no affinity, cpuinfo'       => [['/proc/self/status' => "Name:\tphp\n", '/proc/cpuinfo' => $cpuInfo], 4];
        yield 'cgroup quota of 1.5 CPUs'   => [['/proc/cpuinfo' => $cpuInfo, '/sys/fs/cgroup/cpu.max' => "150000 100000\n"], 2];
        yield 'cgroup without quota'       => [['/proc/cpuinfo' => $cpuInfo, '/sys/fs/cgroup/cpu.max' => "max 100000\n"], 4];
        yield 'quota above the CPUs'       => [['/proc/self/status' => "Cpus_allowed_list:\t0-1\n", '/sys/fs/cgroup/cpu.max' => "800000 100000\n"], 2];
        yield 'quota only'                 => [['/sys/fs/cgroup/cpu.max' => "300000 100000\n"], 3];
        yield 'nothing readable'           => [[], 1];
        yield 'cpuinfo without processors' => [['/proc/cpuinfo' => "garbage\n"], 1];
    }

    /**
     * @param array<string, string> $files
     */
    #[DataProvider('linuxSystems')]
    public function testLinuxCountHonoursAffinityAndQuota(array $files, int $expectedCount): void
    {
        foreach ($files as $path => $content) {
            file_put_contents($this->fileSystem->path($path), $content);
        }

        $this->assertSame($expectedCount, CpuCoreCounter::count('Linux', $this->fileSystem->path('')));
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
