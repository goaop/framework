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

namespace Go\Aop;

use Go\Aop\Exception\InvalidConfigurationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WeavingDriverTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, WeavingDriver}>
     */
    public static function provideOptions(): iterable
    {
        yield 'enum case' => [WeavingDriver::ZEngine, WeavingDriver::ZEngine];
        yield 'stream' => ['stream', WeavingDriver::Stream];
        yield 'zengine' => ['zengine', WeavingDriver::ZEngine];
    }

    #[DataProvider('provideOptions')]
    public function testResolvesTheDriverOption(mixed $option, WeavingDriver $expected): void
    {
        $this->assertSame($expected, WeavingDriver::fromOption($option));
    }

    public function testRefusesAnUnknownDriverName(): void
    {
        try {
            WeavingDriver::fromOption('ffi');
            self::fail('An unknown driver name must be refused');
        } catch (InvalidConfigurationException $exception) {
            $this->assertSame(
                'Option "driver" must be one of "stream", "zengine" (or a Go\Aop\WeavingDriver case), got "ffi".',
                $exception->getMessage(),
            );
        }
    }

    public function testRefusesAValueOfAnotherType(): void
    {
        try {
            WeavingDriver::fromOption(1);
            self::fail('A value of another type must be refused');
        } catch (InvalidConfigurationException $exception) {
            $this->assertStringEndsWith('got int.', $exception->getMessage());
        }
    }
}
