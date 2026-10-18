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

namespace Go\Driver\StreamWrapper\Functional;

use Go\Driver\StreamFilter\StreamFilterDriver;
use Go\Driver\StreamWrapper\StreamWrapperDriver;
use Go\Tests\TestProject\Application\FfiChild;
use Go\Tests\TestProject\Application\FfiParent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * The stream wrapper driver weaves the driver fixtures exactly like the stream filter driver: the probe
 * tests/Fixtures/project/bin/driver-weaving.php runs in subprocesses under both drivers (`stream_wrapper_methods`
 * and `ffi_reference` share the FfiAspectKernel fixtures) and its reports are compared, with opcache off and on
 */
final class StreamWrapperWeavingTest extends TestCase
{
    private const string PROJECT_DIR = __DIR__ . '/../../../../tests/Fixtures/project';

    private const string PROBE = self::PROJECT_DIR . '/bin/driver-weaving.php';

    private const string WRAPPER_CONFIGURATION = 'stream_wrapper_methods';

    private const string REFERENCE_CONFIGURATION = 'ffi_reference';

    /**
     * PHP options of every subprocess: the JIT off (the PHP 8.5 tracing JIT miscompiles the framework), freshly
     * written files cacheable by opcache at once
     */
    private const array PHP_OPTIONS = [
        '-d', 'opcache.jit=off',
        '-d', 'opcache.jit_buffer_size=0',
        '-d', 'opcache.file_update_protection=0',
        '-d', 'opcache.enable=1',
    ];

    protected function setUp(): void
    {
        $this->clearCaches();
    }

    protected function tearDown(): void
    {
        $this->clearCaches();
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function provideOpcacheLegs(): iterable
    {
        yield 'opcache off' => [false];
        yield 'opcache on' => [true];
    }

    #[DataProvider('provideOpcacheLegs')]
    public function testWeavesMethodsLikeTheStreamFilterDriver(bool $opcache): void
    {
        $wrapper   = $this->runProbe(self::WRAPPER_CONFIGURATION, 'weave', $opcache);
        $reference = $this->runProbe(self::REFERENCE_CONFIGURATION, 'weave', $opcache);

        self::assertArrayNotHasKey('exception', $wrapper, 'The stream wrapper driver must weave the scenario');
        self::assertArrayNotHasKey('exception', $reference, 'The stream filter driver must weave the scenario');
        self::assertSame(StreamWrapperDriver::class, $wrapper['driver']);
        self::assertSame(StreamFilterDriver::class, $reference['driver']);

        // Advised calls return the same results, run the same advices in the same order and reflect alike
        self::assertSame($reference['calls'], $wrapper['calls']);
        self::assertSame($reference['log'], $wrapper['log']);
        self::assertNotEmpty($wrapper['log']);
        self::assertSame($reference['reflection'], $wrapper['reflection']);
        // The same transformer chain writes the same cache files
        self::assertSame($reference['cache'], $wrapper['cache']);

        if (!$opcache) {
            return;
        }
        // The warm cache serves the second request, with the woven files already in opcache
        $warm = $this->runProbe(self::WRAPPER_CONFIGURATION, 'weave', true);
        self::assertArrayNotHasKey('exception', $warm, 'The warm cache must serve the scenario');
        self::assertSame($reference['calls'], $warm['calls']);
        self::assertSame($reference['log'], $warm['log']);
    }

    #[DataProvider('provideOpcacheLegs')]
    public function testWeavesFunctionsLikeTheStreamFilterDriver(bool $opcache): void
    {
        $wrapper   = $this->runProbe(self::WRAPPER_CONFIGURATION, 'functions', $opcache);
        $reference = $this->runProbe(self::REFERENCE_CONFIGURATION, 'functions', $opcache);

        self::assertArrayNotHasKey('exception', $wrapper, 'The stream wrapper driver must weave the scenario');
        self::assertArrayNotHasKey('exception', $reference, 'The stream filter driver must weave the scenario');
        self::assertSame($reference['calls'], $wrapper['calls']);
        self::assertSame($reference['log'], $wrapper['log']);
        self::assertNotEmpty($wrapper['log']);
        self::assertIsArray($wrapper['calls']);
        self::assertTrue($wrapper['calls']['functions.proxied'] ?? false, 'The function proxy is declared');
    }

    /**
     * The woven sources are served under their original paths, so opcache caches them like any other file
     * (a php://filter include never is): the point of the driver
     */
    public function testOpcacheCachesTheWovenFilesUnderTheirOriginalPaths(): void
    {
        $script = sprintf(
            'include %s; $child = new %s(); echo json_encode(['
            . '"greet" => $child->greet("Ann"), '
            . '"scripts" => array_keys((array) (opcache_get_status(true)["scripts"] ?? []))'
            . ']);',
            var_export(self::PROJECT_DIR . '/web/index.php', true),
            '\\' . FfiChild::class,
        );
        $expectedScripts = [
            realpath(self::PROJECT_DIR . '/src/Application/' . basename(str_replace('\\', '/', FfiParent::class)) . '.php'),
            realpath(self::PROJECT_DIR . '/src/Application/' . basename(str_replace('\\', '/', FfiChild::class)) . '.php'),
        ];

        // Cold cache (woven now) and warm cache (served from the cache): both runs are cached by opcache
        foreach (['cold', 'warm'] as $run) {
            $process = new Process(
                [self::phpExecutable(), ...self::PHP_OPTIONS, '-d', 'opcache.enable_cli=1', '-r', $script],
                null,
                ['GO_AOP_CONFIGURATION' => self::WRAPPER_CONFIGURATION],
            );
            $process->run();
            self::assertTrue($process->isSuccessful(), $run . ' run: ' . $process->getOutput() . $process->getErrorOutput());
            $report = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($report);
            self::assertSame('HI, ANN FROM CHILD', $report['greet'] ?? null, $run . ' run: the advices ran');
            $scripts = $report['scripts'] ?? null;
            self::assertIsArray($scripts);
            if ($scripts === []) {
                self::markTestSkipped('opcache is not available in the PHP subprocess');
            }
            foreach ($expectedScripts as $expectedScript) {
                self::assertContains($expectedScript, $scripts, $run . ' run: the woven file is cached by its original path');
            }
        }
    }

    /**
     * Runs one probe scenario and returns its report
     *
     * @return array<mixed>
     */
    private function runProbe(string $configuration, string $scenario, bool $opcache): array
    {
        $process = new Process(
            [
                self::phpExecutable(),
                ...self::PHP_OPTIONS,
                '-d', 'opcache.enable_cli=' . ($opcache ? '1' : '0'),
                self::PROBE,
                $scenario,
            ],
            null,
            ['GO_AOP_CONFIGURATION' => $configuration],
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
     * Removes the cache directories of both configurations: every test starts from a cold cache
     */
    private function clearCaches(): void
    {
        /** @var array<string, array{cacheDir: string}> $configurations Shape of our own fixture file is trusted */
        $configurations = require self::PROJECT_DIR . '/web/configuration.php';
        $filesystem     = new Filesystem();
        foreach ([self::WRAPPER_CONFIGURATION, self::REFERENCE_CONFIGURATION] as $name) {
            $filesystem->remove($configurations[$name]['cacheDir']);
        }
    }

    private static function phpExecutable(): string
    {
        $phpExecutable = (new PhpExecutableFinder())->find(false);
        self::assertNotFalse($phpExecutable, 'A PHP executable is needed to run the probe');

        return $phpExecutable;
    }
}
