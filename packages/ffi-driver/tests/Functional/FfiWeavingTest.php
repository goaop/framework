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

namespace Go\Driver\Ffi\Functional;

use Go\Driver\Ffi\Exception\UnsupportedJoinpointException;
use Go\Tests\TestProject\Application\FfiChild;
use Go\Tests\TestProject\Application\FfiFunctions;
use Go\Tests\TestProject\Application\FfiLate;
use Go\Tests\TestProject\Application\FfiParent;
use Go\Tests\TestProject\Application\FfiUnadvised;
use Go\Tests\TestProject\Aspect\FfiUnsupportedAspect;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Go\Driver\Ffi\FfiDriver;
use Go\Driver\StreamFilter\StreamFilterDriver;

/**
 * The ffi driver weaves the fixture project at runtime: same behaviour as the stream driver, donors cached
 */
#[Group('ffi')]
final class FfiWeavingTest extends FfiFunctionalTestCase
{
    /**
     * Files the driver writes into the cache directory of the `weave` scenario
     */
    private const array EXPECTED_CACHE_FILES = [
        '_ffi/_functions/Go/Tests/TestProject/Application.php',
        '_ffi/src/Application/FfiChildOriginalTrait.php',
        '_ffi/src/Application/FfiParentOriginalTrait.php',
        'src/Aspect/FfiAspect.cache.php',
    ];

    #[DataProvider('provideOpcacheLegs')]
    public function testWeavesLikeTheStreamDriver(bool $opcache): void
    {
        $engine = $this->runProbe('ffi', 'weave', $opcache);
        $stream = $this->runProbe('ffi_reference', 'weave', $opcache);

        self::assertArrayNotHasKey('exception', $engine, 'The engine driver must weave the scenario');
        self::assertArrayNotHasKey('exception', $stream, 'The stream driver must weave the scenario');
        self::assertSame(FfiDriver::class, $engine['driver']);
        self::assertSame(StreamFilterDriver::class, $stream['driver']);

        // Advised calls return the same results and run the same advices in the same order
        self::assertSame($stream['calls'], $engine['calls']);
        self::assertSame($stream['log'], $engine['log']);
        self::assertNotEmpty($engine['log']);

        // Reflection agrees too, except that a swapped body carries the file it was compiled in: the donor.
        // The woven class is marked as a Go\Aop\Proxy like a generated proxy class, an unadvised one is not
        $expectedReflection = $stream['reflection'];
        self::assertIsArray($expectedReflection);
        $expectedReflection['parent.greet.file'] = 'FfiParentOriginalTrait.php';
        self::assertSame($expectedReflection, $engine['reflection']);
        self::assertTrue(self::at($engine, 'reflection', 'child.proxy'));
        self::assertFalse(self::at($engine, 'reflection', 'unadvised.proxy'));

        // Own methods are swapped, inherited public methods get an override, the unadvised class is left alone
        self::assertSame(
            ['outcome' => 'already-woven', 'methods' => ['greet'], 'reason' => null],
            self::at($engine, 'weave', FfiParent::class),
        );
        self::assertSame(
            ['outcome' => 'already-woven', 'methods' => ['greet', 'withDefault', 'getCalls', 'describe', 'callSecret'], 'reason' => null],
            self::at($engine, 'weave', FfiChild::class),
        );
        self::assertSame(
            ['outcome' => 'no-advices', 'methods' => [], 'reason' => null],
            self::at($engine, 'weave', FfiUnadvised::class),
        );
        self::assertSame([FfiParent::class, FfiChild::class], $engine['donors']);
        self::assertSame(self::EXPECTED_CACHE_FILES, $engine['cache']);

        // The subprocess runs with the JIT off, nothing for the guard to do
        self::assertSame(['jitWasActive' => false, 'previousMode' => null, 'disabledAtRuntime' => false], self::at($engine, 'boot', 'jit'));
        self::assertSame($opcache, self::at($engine, 'boot', 'opcache'));
    }

    /**
     * Function interception: the namespaced proxies of the stream driver, declared right after a class of the
     * namespace is loaded; a warm request includes the recorded proxy file without the aspects
     */
    #[DataProvider('provideOpcacheLegs')]
    public function testInterceptsFunctionsLikeTheStreamDriver(bool $opcache): void
    {
        $engine = $this->runProbe('ffi', 'functions', $opcache);
        $stream = $this->runProbe('ffi_reference', 'functions', $opcache);

        self::assertArrayNotHasKey('exception', $engine, 'The engine driver must run the scenario');
        self::assertArrayNotHasKey('exception', $stream, 'The stream driver must run the scenario');
        self::assertSame($stream['calls'], $engine['calls']);
        self::assertSame($stream['log'], $engine['log']);
        self::assertSame(['around array_sum with [[1,2,3]]'], $engine['log'], 'Only the unqualified call is intercepted');
        self::assertTrue(self::at($engine, 'calls', 'functions.proxied'));
        self::assertSame(
            ['outcome' => 'no-advices', 'methods' => [], 'reason' => null],
            self::at($engine, 'weave', FfiFunctions::class),
            'No method advice matches the class: the function proxies need no donor',
        );
        self::assertSame([], $engine['donors']);

        $proxyFile = self::cacheDir('ffi') . '/_ffi/_functions/Go/Tests/TestProject/Application.php';
        self::assertFileExists($proxyFile);
        file_put_contents($proxyFile, PHP_EOL . '// marked by ' . self::class . PHP_EOL, FILE_APPEND);
        $markedProxy = md5_file($proxyFile);

        $warm = $this->runProbe('ffi', 'functions', $opcache);
        self::assertTrue($warm['indexExisted']);
        self::assertSame($engine['calls'], $warm['calls']);
        self::assertSame($engine['log'], $warm['log']);
        self::assertSame($markedProxy, md5_file($proxyFile), 'A fresh record reuses its proxy file');
    }

    #[DataProvider('provideOpcacheLegs')]
    public function testWarmRequestReusesTheDonorAndStaleSourceRegeneratesIt(bool $opcache): void
    {
        $donorFile = self::cacheDir('ffi') . '/_ffi/src/Application/FfiChildOriginalTrait.php';
        $source    = realpath(self::PROJECT_DIR . '/src/Application/FfiChild.php');
        self::assertNotFalse($source);

        $cold = $this->runProbe('ffi', 'weave', $opcache);
        self::assertFalse($cold['indexExisted']);
        self::assertFileExists($donorFile);
        self::assertFileExists(self::cacheDir('ffi') . '/_ffi.cache', 'The donor index is flushed at the end of the request');

        // A marked donor file proves that the warm request loads the cached donor instead of generating it again
        file_put_contents($donorFile, PHP_EOL . '// marked by ' . self::class . PHP_EOL, FILE_APPEND);
        $markedDonor = md5_file($donorFile);

        $warm = $this->runProbe('ffi', 'weave', $opcache);
        self::assertTrue($warm['indexExisted']);
        self::assertSame($cold['calls'], $warm['calls']);
        self::assertSame($cold['log'], $warm['log']);
        self::assertSame($markedDonor, md5_file($donorFile), 'A fresh record reuses its donor');

        // Another mtime of the source (a deployment restoring an older file) makes the record stale
        $sourceMtime = filemtime($source);
        self::assertNotFalse($sourceMtime);
        touch($source, $sourceMtime - 60);
        try {
            $stale = $this->runProbe('ffi', 'weave', $opcache);
        } finally {
            touch($source, $sourceMtime);
        }
        self::assertSame($cold['calls'], $stale['calls']);
        self::assertNotSame($markedDonor, md5_file($donorFile), 'A stale record regenerates its donor');
    }

    #[DataProvider('provideOpcacheLegs')]
    public function testProductionModeReusesTheIndex(bool $opcache): void
    {
        $cold = $this->runProbe('ffi_production', 'weave', $opcache);
        $warm = $this->runProbe('ffi_production', 'weave', $opcache);

        self::assertArrayNotHasKey('exception', $cold);
        self::assertFalse($cold['indexExisted']);
        self::assertTrue($warm['indexExisted']);
        self::assertSame($cold['calls'], $warm['calls']);
        self::assertSame($cold['log'], $warm['log']);
        self::assertSame([FfiParent::class, FfiChild::class], $warm['donors']);
    }

    #[DataProvider('provideOpcacheLegs')]
    public function testWeavesAClassDeclaredBeforeTheKernelOnDemand(bool $opcache): void
    {
        $report = $this->runProbe('ffi', 'late', $opcache);

        self::assertArrayNotHasKey('exception', $report);
        self::assertSame(
            ['outcome' => 'woven', 'methods' => ['compute'], 'reason' => null],
            self::at($report, 'weave', FfiLate::class),
        );
        self::assertSame(['late.compute' => 5], $report['calls']);
        self::assertSame(['before ' . FfiLate::class . '::compute on ' . FfiLate::class], $report['log']);
        self::assertSame([FfiLate::class], $report['donors']);
    }

    public function testRefusesJoinpointKindsTheEngineCannotWeave(): void
    {
        $report = $this->runProbe('ffi_unsupported', 'unsupported', false);

        self::assertArrayHasKey('exception', $report);
        self::assertSame(UnsupportedJoinpointException::class, self::at($report, 'exception', 'class'));
        self::assertStringContainsString(FfiUnsupportedAspect::class . '->beforeCounterAccess', self::stringAt($report, 'exception', 'message'));
        self::assertStringContainsString('join point kind(s) prop', self::stringAt($report, 'exception', 'message'));
        self::assertSame([], $report['donors'], 'No donor is generated for a refused class');
        self::assertSame([], $report['log']);
    }

    public function testCacheWarmupRefusesTheEngineDriver(): void
    {
        [$exitCode, $output, $errorOutput] = $this->runConsole('ffi', 'cache:warmup:aop');

        self::assertSame(1, $exitCode, $output . $errorOutput);
        self::assertStringContainsString(FfiDriver::class . ' never uses', $output . $errorOutput);
    }

    public function testJitGuardSwitchesAnActiveJitOffAtRuntime(): void
    {
        $report = $this->runProbe('ffi', 'weave', true, ['-d', 'opcache.jit=tracing', '-d', 'opcache.jit_buffer_size=64M']);

        if (isset($report['exception'])) {
            self::fail('An active JIT must be switched off at runtime: ' . json_encode($report['exception']));
        }
        if (self::at($report, 'boot', 'jit', 'jitWasActive') !== true) {
            self::markTestSkipped('The JIT cannot be enabled in this PHP build, the guard has nothing to do');
        }
        self::assertSame(['jitWasActive' => true, 'previousMode' => 'tracing', 'disabledAtRuntime' => true], self::at($report, 'boot', 'jit'));
        self::assertSame('HI, BOB FROM CHILD', self::at($report, 'calls', 'child.greet'));
    }
}
