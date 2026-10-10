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

use Go\Aop\Exception\InvalidConfigurationException;
use Go\Aop\Exception\UnsupportedJoinpointException;
use Go\Tests\TestProject\Application\ZEngineChild;
use Go\Tests\TestProject\Application\ZEngineLate;
use Go\Tests\TestProject\Application\ZEngineParent;
use Go\Tests\TestProject\Application\ZEngineUnadvised;
use Go\Tests\TestProject\Aspect\ZEngineUnsupportedAspect;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * The z-engine driver weaves the fixture project at runtime: same behaviour as the stream driver, donors cached
 */
#[Group('zengine')]
final class ZEngineWeavingTest extends ZEngineFunctionalTestCase
{
    /**
     * Files the driver writes into the cache directory of the `weave` scenario
     */
    private const array EXPECTED_CACHE_FILES = [
        '_zengine/src/Application/ZEngineChild__AopDonor.php',
        '_zengine/src/Application/ZEngineParent__AopDonor.php',
        'src/Aspect/ZEngineAspect.cache.php',
    ];

    #[DataProvider('provideOpcacheLegs')]
    public function testWeavesLikeTheStreamDriver(bool $opcache): void
    {
        $engine = $this->runProbe('zengine', 'weave', $opcache);
        $stream = $this->runProbe('zengine_reference', 'weave', $opcache);

        self::assertArrayNotHasKey('exception', $engine, 'The engine driver must weave the scenario');
        self::assertArrayNotHasKey('exception', $stream, 'The stream driver must weave the scenario');
        self::assertSame('zengine', $engine['driver']);
        self::assertSame('stream', $stream['driver']);

        // Advised calls return the same results and run the same advices in the same order
        self::assertSame($stream['calls'], $engine['calls']);
        self::assertSame($stream['log'], $engine['log']);
        self::assertNotEmpty($engine['log']);

        // Reflection agrees too, except that a swapped body carries the file it was compiled in: the donor
        $expectedReflection = $stream['reflection'];
        self::assertIsArray($expectedReflection);
        $expectedReflection['parent.greet.file'] = 'ZEngineParent__AopDonor.php';
        self::assertSame($expectedReflection, $engine['reflection']);

        // Own methods are swapped, inherited public methods get an override, the unadvised class is left alone
        self::assertSame(
            ['outcome' => 'already-woven', 'methods' => ['greet'], 'reason' => null],
            self::at($engine, 'weave', ZEngineParent::class),
        );
        self::assertSame(
            ['outcome' => 'already-woven', 'methods' => ['greet', 'withDefault', 'getCalls', 'describe', 'callSecret'], 'reason' => null],
            self::at($engine, 'weave', ZEngineChild::class),
        );
        self::assertSame(
            ['outcome' => 'no-advices', 'methods' => [], 'reason' => null],
            self::at($engine, 'weave', ZEngineUnadvised::class),
        );
        self::assertSame([ZEngineParent::class, ZEngineChild::class], $engine['donors']);
        self::assertSame(self::EXPECTED_CACHE_FILES, $engine['cache']);

        // The subprocess runs with the JIT off, nothing for the guard to do
        self::assertSame(['jitWasActive' => false, 'previousMode' => null, 'disabledAtRuntime' => false], self::at($engine, 'boot', 'jit'));
        self::assertSame($opcache, self::at($engine, 'boot', 'opcache'));
    }

    #[DataProvider('provideOpcacheLegs')]
    public function testWarmRequestReusesTheDonorAndStaleSourceRegeneratesIt(bool $opcache): void
    {
        $donorFile = self::cacheDir('zengine') . '/_zengine/src/Application/ZEngineChild__AopDonor.php';
        $source    = realpath(self::PROJECT_DIR . '/src/Application/ZEngineChild.php');
        self::assertNotFalse($source);

        $cold = $this->runProbe('zengine', 'weave', $opcache);
        self::assertFalse($cold['indexExisted']);
        self::assertFileExists($donorFile);
        self::assertFileExists(self::cacheDir('zengine') . '/_zengine.cache', 'The donor index is flushed at the end of the request');

        // A marked donor file proves that the warm request loads the cached donor instead of generating it again
        file_put_contents($donorFile, PHP_EOL . '// marked by ' . self::class . PHP_EOL, FILE_APPEND);
        $markedDonor = md5_file($donorFile);

        $warm = $this->runProbe('zengine', 'weave', $opcache);
        self::assertTrue($warm['indexExisted']);
        self::assertSame($cold['calls'], $warm['calls']);
        self::assertSame($cold['log'], $warm['log']);
        self::assertSame($markedDonor, md5_file($donorFile), 'A fresh record reuses its donor');

        // Another mtime of the source (a deployment restoring an older file) makes the record stale
        $sourceMtime = filemtime($source);
        self::assertNotFalse($sourceMtime);
        touch($source, $sourceMtime - 60);
        try {
            $stale = $this->runProbe('zengine', 'weave', $opcache);
        } finally {
            touch($source, $sourceMtime);
        }
        self::assertSame($cold['calls'], $stale['calls']);
        self::assertNotSame($markedDonor, md5_file($donorFile), 'A stale record regenerates its donor');
    }

    #[DataProvider('provideOpcacheLegs')]
    public function testProductionModeReusesTheIndex(bool $opcache): void
    {
        $cold = $this->runProbe('zengine_production', 'weave', $opcache);
        $warm = $this->runProbe('zengine_production', 'weave', $opcache);

        self::assertArrayNotHasKey('exception', $cold);
        self::assertFalse($cold['indexExisted']);
        self::assertTrue($warm['indexExisted']);
        self::assertSame($cold['calls'], $warm['calls']);
        self::assertSame($cold['log'], $warm['log']);
        self::assertSame([ZEngineParent::class, ZEngineChild::class], $warm['donors']);
    }

    #[DataProvider('provideOpcacheLegs')]
    public function testWeavesAClassDeclaredBeforeTheKernelOnDemand(bool $opcache): void
    {
        $report = $this->runProbe('zengine', 'late', $opcache);

        self::assertArrayNotHasKey('exception', $report);
        self::assertSame(
            ['outcome' => 'woven', 'methods' => ['compute'], 'reason' => null],
            self::at($report, 'weave', ZEngineLate::class),
        );
        self::assertSame(['late.compute' => 5], $report['calls']);
        self::assertSame(['before ' . ZEngineLate::class . '::compute on ' . ZEngineLate::class], $report['log']);
        self::assertSame([ZEngineLate::class], $report['donors']);
    }

    public function testRefusesJoinpointKindsTheEngineCannotWeave(): void
    {
        $report = $this->runProbe('zengine_unsupported', 'unsupported', false);

        self::assertArrayHasKey('exception', $report);
        self::assertSame(UnsupportedJoinpointException::class, self::at($report, 'exception', 'class'));
        self::assertStringContainsString(ZEngineUnsupportedAspect::class . '->beforeCounterAccess', self::stringAt($report, 'exception', 'message'));
        self::assertStringContainsString('join point kind(s) prop', self::stringAt($report, 'exception', 'message'));
        self::assertSame([], $report['donors'], 'No donor is generated for a refused class');
        self::assertSame([], $report['log']);
    }

    public function testCacheWarmupRefusesTheEngineDriver(): void
    {
        [$exitCode, $output, $errorOutput] = $this->runConsole('zengine', 'cache:warmup:aop');

        self::assertSame(1, $exitCode, $output . $errorOutput);
        self::assertStringContainsString('zengine weaving driver', $output . $errorOutput);
        self::assertStringContainsString('stream filter', $output . $errorOutput);
    }

    public function testJitGuardHandlesATracingJitAccordingToThePhpVersion(): void
    {
        $report = $this->runProbe('zengine', 'weave', true, ['-d', 'opcache.jit=tracing', '-d', 'opcache.jit_buffer_size=64M']);

        if (PHP_VERSION_ID >= 80500 && PHP_VERSION_ID < 80600) {
            if (isset($report['exception'])) {
                self::fail('The JIT must be switched off at runtime on PHP 8.5: ' . json_encode($report['exception']));
            }
            if (self::at($report, 'boot', 'jit', 'jitWasActive') !== true) {
                self::markTestSkipped('The JIT cannot be enabled in this PHP build, the guard has nothing to do');
            }
            self::assertSame(['jitWasActive' => true, 'previousMode' => 'tracing', 'disabledAtRuntime' => true], self::at($report, 'boot', 'jit'));
            self::assertSame('HI, BOB FROM CHILD', self::at($report, 'calls', 'child.greet'));

            return;
        }
        if (!isset($report['exception'])) {
            self::markTestSkipped('The JIT cannot be enabled in this PHP build, the guard has nothing to do');
        }
        self::assertSame(InvalidConfigurationException::class, self::at($report, 'exception', 'class'));
        self::assertStringContainsString('opcache.jit=off', self::stringAt($report, 'exception', 'message'));
    }
}
