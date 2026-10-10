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

namespace Go\Instrument\ZEngine\InProcess;

use Go\Aop\WeavingDriver;
use Go\Instrument\ClassLoading\ZEngineComposerLoader;
use Go\Instrument\ZEngine\DonorCacheIndex;
use Go\Instrument\ZEngine\MethodTableRewriter;
use Go\Instrument\ZEngine\ZEngineClassWeaver;
use Go\Instrument\ZEngine\ZEngineDriver;
use PHPUnit\Framework\Attributes\Group;

/**
 * The boot of the zengine driver inside the test runner
 */
#[Group('zengine')]
final class ZEngineDriverInProcessTest extends ZEngineInProcessTestCase
{
    public function testBootRegistersTheDriverAndReportsWhatItDid(): void
    {
        $kernel    = $this->bootKernel();
        $container = $kernel->getContainer();

        $report = ZEngineDriver::getBootReport($container);
        $this->assertSame('zengine', $report['driver']);
        $this->assertSame(PHP_VERSION, $report['php']);
        $this->assertSame(function_exists('opcache_get_status') && opcache_get_status(false) !== false, $report['opcache']);
        // The tests skip when the JIT is active, so the guard had nothing to do
        $this->assertSame(['jitWasActive' => false, 'previousMode' => null, 'disabledAtRuntime' => false], $report['jit']);

        foreach ([ZEngineClassWeaver::class, DonorCacheIndex::class, MethodTableRewriter::class] as $service) {
            $this->assertTrue($container->has($service), $service . ' is registered by the boot');
        }
        $this->assertSame($container->getService(ZEngineClassWeaver::class), $container->getService(ZEngineClassWeaver::class), 'One weaver per container');

        $wrappers = 0;
        foreach (spl_autoload_functions() as $loader) {
            if (is_array($loader) && $loader[0] instanceof ZEngineComposerLoader) {
                $wrappers++;
            }
        }
        $this->assertGreaterThan(0, $wrappers, "Composer's loader is wrapped by the engine loader");

        // A second init() of the same kernel is a no-op
        $kernel->init(['driver' => 'stream', 'cacheDir' => $this->cacheDir]);
        $this->assertSame(WeavingDriver::ZEngine, $kernel->getOptions()['driver']);
    }
}
