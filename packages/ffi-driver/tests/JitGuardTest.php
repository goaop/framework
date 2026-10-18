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

use Go\Driver\Ffi\Exception\EngineConfigurationException;
use PHPUnit\Framework\TestCase;

final class JitGuardTest extends TestCase
{
    /**
     * @var list<array{string, string}>
     */
    private array $iniWrites = [];

    public function testNothingToDoWhenTheJitIsOff(): void
    {
        $report = JitGuard::enforce(self::jitStatus(false), $this->iniWriter());

        $this->assertSame(['jitWasActive' => false, 'previousMode' => null, 'disabledAtRuntime' => false], $report);
        $this->assertSame([], $this->iniWrites);
    }

    public function testNothingToDoWithoutOpcache(): void
    {
        $report = JitGuard::enforce(static fn(): false => false, $this->iniWriter());

        $this->assertSame(['jitWasActive' => false, 'previousMode' => null, 'disabledAtRuntime' => false], $report);
        $this->assertSame([], $this->iniWrites);
    }

    public function testAnActiveJitIsSwitchedOffAtRuntime(): void
    {
        $readings = [true, false];
        $reader   = static function () use (&$readings): array {
            return ['jit' => ['on' => array_shift($readings), 'kind' => 'tracing']];
        };

        $report = JitGuard::enforce($reader, $this->iniWriter('tracing'));

        $this->assertSame(['jitWasActive' => true, 'previousMode' => 'tracing', 'disabledAtRuntime' => true], $report);
        $this->assertSame([['opcache.jit', 'off']], $this->iniWrites);
    }

    public function testAJitThatStaysOnAfterTheSwitchIsRefused(): void
    {
        try {
            JitGuard::enforce(self::jitStatus(true), $this->iniWriter('tracing'));
            self::fail('A JIT that stays on must be refused');
        } catch (EngineConfigurationException $exception) {
            $this->assertStringContainsString('could not switch it off at runtime', $exception->getMessage());
            $this->assertStringContainsString('opcache.jit=off and opcache.jit_buffer_size=0', $exception->getMessage());
        }
        $this->assertSame([['opcache.jit', 'off']], $this->iniWrites);
    }

    public function testDefaultsDescribeTheRunningProcess(): void
    {
        $status = function_exists('opcache_get_status') ? opcache_get_status(false) : false;
        $jitOn  = is_array($status) && is_array($status['jit'] ?? null) && ($status['jit']['on'] ?? false) === true;
        if ($jitOn) {
            self::markTestSkipped('The JIT is active in the test runner: the guard would change the process');
        }

        $this->assertSame(['jitWasActive' => false, 'previousMode' => null, 'disabledAtRuntime' => false], JitGuard::enforce());
    }

    /**
     * @return \Closure(): array<string, mixed>
     */
    private static function jitStatus(bool $jitOn): \Closure
    {
        return static fn(): array => ['opcache_enabled' => true, 'jit' => ['enabled' => true, 'on' => $jitOn, 'kind' => 'tracing']];
    }

    /**
     * @return \Closure(string, string): (string|false)
     */
    private function iniWriter(string|false $previous = false): \Closure
    {
        return function (string $option, string $value) use ($previous): string|false {
            $this->iniWrites[] = [$option, $value];

            return $previous;
        };
    }
}
