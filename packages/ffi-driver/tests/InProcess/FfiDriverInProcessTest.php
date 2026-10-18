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

namespace Go\Driver\Ffi\InProcess;

use Go\Driver\Ffi\FfiComposerLoader;
use Go\Driver\Ffi\DonorCacheIndex;
use Go\Driver\Ffi\MethodTableRewriter;
use Go\Driver\Ffi\ClassWeaver;
use Go\Driver\Ffi\FfiDriver;
use PHPUnit\Framework\Attributes\Group;

/**
 * The boot of the ffi driver inside the test runner
 */
#[Group('ffi')]
final class FfiDriverInProcessTest extends InProcessTestCase
{
    public function testBootRegistersTheDriverAndReportsWhatItDid(): void
    {
        $kernel    = $this->bootKernel();
        $container = $kernel->getContainer();

        $report = FfiDriver::getBootReport($container);
        $this->assertSame(FfiDriver::class, $report['driver']);
        $this->assertSame(PHP_VERSION, $report['php']);
        $this->assertSame(function_exists('opcache_get_status') && opcache_get_status(false) !== false, $report['opcache']);
        // The tests skip when the JIT is active, so the guard had nothing to do
        $this->assertSame(['jitWasActive' => false, 'previousMode' => null, 'disabledAtRuntime' => false], $report['jit']);

        foreach ([ClassWeaver::class, DonorCacheIndex::class, MethodTableRewriter::class] as $service) {
            $this->assertTrue($container->has($service), $service . ' is registered by the boot');
        }
        $this->assertSame($container->getService(ClassWeaver::class), $container->getService(ClassWeaver::class), 'One weaver per container');

        $wrappers = 0;
        foreach (spl_autoload_functions() as $loader) {
            if (is_array($loader) && $loader[0] instanceof FfiComposerLoader) {
                $wrappers++;
            }
        }
        $this->assertGreaterThan(0, $wrappers, "Composer's loader is wrapped by the engine loader");

        // A second init() of the same kernel is a no-op
        $kernel->init(['driver' => \Go\Driver\StreamFilter\StreamFilterDriver::class, 'cacheDir' => $this->cacheDir]);
        $this->assertSame(FfiDriver::class, $kernel->getOptions()['driver']);
    }
}
