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

namespace Go\Functional\ZEngine;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Base of the z-engine driver tests: every scenario runs the probe tests/Fixtures/project/bin/zengine-weaving.php
 * in a PHP subprocess with FFI enabled and the JIT off, and reads back its JSON report
 *
 * The group `zengine` (declared on every concrete test class, PHPUnit 13 does not inherit it) is excluded from the
 * default run (`composer test:zengine` runs it) and self-skips on a host where the engine cannot boot: z-engine not
 * installed, ext-ffi missing, or a z-engine branch of another PHP minor.
 */
abstract class ZEngineFunctionalTestCase extends TestCase
{
    protected const string PROJECT_DIR = __DIR__ . '/../../Fixtures/project';

    protected const string PROBE = self::PROJECT_DIR . '/bin/zengine-weaving.php';

    /**
     * PHP options every subprocess runs with: FFI on, the JIT off (z-engine hooks the executor internals the JIT
     * bypasses, and the PHP 8.5 tracing JIT miscompiles the framework), freshly written donor files cacheable at once
     */
    protected const array PHP_OPTIONS = [
        '-d', 'ffi.enable=1',
        '-d', 'opcache.jit=off',
        '-d', 'opcache.jit_buffer_size=0',
        '-d', 'opcache.file_update_protection=0',
        '-d', 'opcache.enable=1',
    ];

    private static ?string $engineUnavailableReason = null;

    private static bool $engineChecked = false;

    protected function setUp(): void
    {
        $reason = self::engineUnavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        $this->clearCaches();
    }

    protected function tearDown(): void
    {
        $this->clearCaches();
    }

    /**
     * Both opcache legs: the engine mutation differs for a class published in shared memory
     *
     * @return iterable<string, array{bool}>
     */
    public static function provideOpcacheLegs(): iterable
    {
        yield 'opcache off' => [false];
        yield 'opcache on' => [true];
    }

    /**
     * Runs one probe scenario and returns its report
     *
     * @param string       $configuration Configuration name in tests/Fixtures/project/web/configuration.php
     * @param string       $scenario      Scenario of the probe
     * @param bool         $opcache       Whether opcache is active in the subprocess
     * @param list<string> $extraOptions  Further command-line options of the PHP subprocess
     *
     * @return array<mixed>
     */
    protected function runProbe(string $configuration, string $scenario, bool $opcache, array $extraOptions = []): array
    {
        $process = new Process(
            [
                self::phpExecutable(),
                ...self::PHP_OPTIONS,
                '-d', 'opcache.enable_cli=' . ($opcache ? '1' : '0'),
                ...$extraOptions,
                self::PROBE,
                $scenario,
            ],
            null,
            ['GO_AOP_CONFIGURATION' => $configuration, 'ZENGINE_AUTOBOOT' => '0'],
        );
        $process->run();
        self::assertTrue($process->isSuccessful(), sprintf(
            "The probe failed (%s, %s):\n%s\n%s",
            $configuration,
            $scenario,
            $process->getOutput(),
            $process->getErrorOutput(),
        ));

        $report = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($report);
        if ($opcache && ($report['opcache'] ?? false) !== true) {
            self::markTestSkipped('opcache is not available in the PHP subprocess, the opcache leg cannot run');
        }

        return $report;
    }

    /**
     * Returns a value of the report by its path of keys, failing on a missing key
     *
     * @param array<mixed> $report
     */
    protected static function at(array $report, string ...$path): mixed
    {
        $value = $report;
        foreach ($path as $key) {
            self::assertIsArray($value, 'Report path ' . implode('.', $path) . ' leads through a non-array');
            self::assertArrayHasKey($key, $value, 'Report path ' . implode('.', $path) . ' is missing');
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Returns a string value of the report by its path of keys
     *
     * @param array<mixed> $report
     */
    protected static function stringAt(array $report, string ...$path): string
    {
        $value = self::at($report, ...$path);
        self::assertIsString($value, 'Report path ' . implode('.', $path) . ' is not a string');

        return $value;
    }

    /**
     * Runs a console command of the fixture project with the engine configuration, returns exit code, stdout, stderr
     *
     * @param list<string> $arguments
     *
     * @return array{int|null, string, string}
     */
    protected function runConsole(string $configuration, string $command, array $arguments = []): array
    {
        $process = new Process(
            [
                self::phpExecutable(),
                ...self::PHP_OPTIONS,
                '-d', 'opcache.enable_cli=0',
                self::PROJECT_DIR . '/bin/console',
                '--no-ansi',
                $command,
                self::PROJECT_DIR . '/web/index.php',
                ...$arguments,
            ],
            null,
            // A wide terminal: the console wraps exception messages to the terminal width, mid-word
            ['GO_AOP_CONFIGURATION' => $configuration, 'ZENGINE_AUTOBOOT' => '0', 'COLUMNS' => '1000'],
        );
        $process->run();

        return [$process->getExitCode(), $process->getOutput(), $process->getErrorOutput()];
    }

    /**
     * Cache directory of a fixture configuration
     */
    protected static function cacheDir(string $configuration): string
    {
        /** @var array<string, array{cacheDir: string}> $configurations Shape of our own fixture file is trusted */
        $configurations = require self::PROJECT_DIR . '/web/configuration.php';

        return $configurations[$configuration]['cacheDir'];
    }

    /**
     * Removes the cache directories of every z-engine configuration
     */
    protected function clearCaches(): void
    {
        /** @var array<string, array{cacheDir: string}> $configurations Shape of our own fixture file is trusted */
        $configurations = require self::PROJECT_DIR . '/web/configuration.php';
        $filesystem     = new Filesystem();
        foreach ($configurations as $name => $configuration) {
            if (str_starts_with($name, 'zengine') && $filesystem->exists($configuration['cacheDir'])) {
                $filesystem->remove($configuration['cacheDir']);
            }
        }
    }

    /**
     * Why the engine cannot run on this host, null when it can (checked once per process, in a subprocess booting
     * z-engine exactly like the probe: ext-ffi, ffi.enable and the PHP minor of the installed z-engine branch)
     */
    private static function engineUnavailableReason(): ?string
    {
        if (self::$engineChecked) {
            return self::$engineUnavailableReason;
        }
        self::$engineChecked = true;
        if (!class_exists(\ZEngine\Core::class)) {
            return self::$engineUnavailableReason = 'lisachenko/z-engine is not installed: '
                . 'composer require --dev lisachenko/z-engine:dev-feature/redefine-preserve-alias (see docs/zengine-driver.md)';
        }
        $process = new Process(
            [
                self::phpExecutable(),
                ...self::PHP_OPTIONS,
                '-d', 'opcache.enable_cli=0',
                '-r',
                'require $argv[1]; ZEngine\Core::init(); echo "booted";',
                __DIR__ . '/../../../vendor/autoload.php',
            ],
            null,
            ['ZENGINE_AUTOBOOT' => '0'],
        );
        $process->run();
        if (!$process->isSuccessful() || !str_contains($process->getOutput(), 'booted')) {
            $failure = trim($process->getErrorOutput() . "\n" . $process->getOutput());

            return self::$engineUnavailableReason = 'z-engine cannot boot on this host (ext-ffi, ffi.enable=1 and the '
                . 'z-engine branch of this PHP minor are required): ' . strtok($failure, "\n");
        }

        return self::$engineUnavailableReason = null;
    }

    private static function phpExecutable(): string
    {
        $phpExecutable = (new PhpExecutableFinder())->find(false);
        self::assertNotFalse($phpExecutable, 'A PHP executable is needed to run the probe');

        return $phpExecutable;
    }
}
