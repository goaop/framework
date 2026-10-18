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

namespace Go\Driver\Ffi;

use Go\Aop\Features;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\Container;
use Go\Driver\Ffi\Exception\EngineConfigurationException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The boot refusals of the driver: without the engine, with an engine that cannot start, or with a feature the
 * driver cannot honour (the successful boot is covered in-process, see InProcess/FfiDriverInProcessTest)
 */
#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
#[Group('ffi')]
final class FfiDriverTest extends TestCase
{
    public function testRefusesToBootWithoutZEngine(): void
    {
        $container = $this->createMock(AspectContainer::class);
        $container->expects($this->never())->method('add');

        try {
            new FfiDriver(static fn(): bool => false)->boot($this->kernel(0), $container);
            self::fail('A missing engine must be refused');
        } catch (EngineConfigurationException $refusal) {
            $this->assertStringContainsString('requires lisachenko/z-engine', $refusal->getMessage());
            $this->assertStringContainsString('composer require lisachenko/z-engine', $refusal->getMessage());
        }
    }

    public function testRefusesIncludeInterception(): void
    {
        $container = $this->createMock(AspectContainer::class);
        $container->expects($this->never())->method('add');

        try {
            new FfiDriver(static fn(): bool => true)->boot($this->kernel(Features::INTERCEPT_INCLUDES), $container);
            self::fail('Include interception is a source transformation the driver cannot provide');
        } catch (EngineConfigurationException $refusal) {
            $this->assertStringContainsString('Features::INTERCEPT_INCLUDES', $refusal->getMessage());
        }
    }

    public function testWrapsAnEngineThatCannotBoot(): void
    {
        $status = function_exists('opcache_get_status') ? opcache_get_status(false) : false;
        if (is_array($status) && is_array($status['jit'] ?? null) && ($status['jit']['on'] ?? false) === true) {
            self::markTestSkipped('The opcache JIT is active in the test runner: the guard runs before the engine boot');
        }
        $container = $this->createMock(AspectContainer::class);
        $container->expects($this->never())->method('add');
        $reason = new RuntimeException('FFI is not enabled');

        try {
            new FfiDriver(
                static fn(): bool => true,
                static function () use ($reason): void {
                    throw $reason;
                },
            )->boot($this->kernel(0), $container);
            self::fail('An engine that cannot boot must be refused');
        } catch (EngineConfigurationException $refusal) {
            $this->assertSame('The ffi weaving driver cannot boot z-engine: FFI is not enabled', $refusal->getMessage());
            $this->assertSame($reason, $refusal->getPrevious());
        }
    }

    private function kernel(int $features): AspectKernel
    {
        $kernel = $this->createStub(AspectKernel::class);
        $kernel->method('getOptions')->willReturn([
            'debug'          => true,
            'appDir'         => __DIR__,
            'cacheDir'       => __DIR__ . '/cache',
            'cacheFileMode'  => 0770,
            'features'       => $features,
            'includePaths'   => [],
            'excludePaths'   => [],
            'containerClass' => Container::class,
            'driver'         => FfiDriver::class,
        ]);

        return $kernel;
    }
}
