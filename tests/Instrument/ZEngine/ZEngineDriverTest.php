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

namespace Go\Instrument\ZEngine;

use Go\Aop\Exception\InvalidConfigurationException;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The boot refusals of the driver: without the engine, or with an engine that cannot start (the successful boot
 * is covered in-process, see InProcess/ZEngineDriverInProcessTest)
 */
#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class ZEngineDriverTest extends TestCase
{
    public function testRefusesToBootWithoutZEngine(): void
    {
        $container = $this->createMock(AspectContainer::class);
        $container->expects($this->never())->method('add');

        try {
            ZEngineDriver::boot($this->createStub(AspectKernel::class), $container, static fn(): bool => false);
            self::fail('A missing engine must be refused');
        } catch (InvalidConfigurationException $refusal) {
            $this->assertStringContainsString('requires lisachenko/z-engine', $refusal->getMessage());
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
            ZEngineDriver::boot(
                $this->createStub(AspectKernel::class),
                $container,
                static fn(): bool => true,
                static function () use ($reason): void {
                    throw $reason;
                },
            );
            self::fail('An engine that cannot boot must be refused');
        } catch (InvalidConfigurationException $refusal) {
            $this->assertSame('The zengine weaving driver cannot boot z-engine: FFI is not enabled', $refusal->getMessage());
            $this->assertSame($reason, $refusal->getPrevious());
        }
    }
}
