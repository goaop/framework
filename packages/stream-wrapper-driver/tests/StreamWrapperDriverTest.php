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

namespace Go\Driver\StreamWrapper;

use Go\Aop\Features;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\Container;
use Go\Driver\SourceTransformingDriver;
use Go\Driver\StreamWrapper\Exception\StreamWrapperConfigurationException;
use PHPUnit\Framework\TestCase;

/**
 * The boot of the driver: the include interception flag is refused, otherwise the file wrapper is replaced
 */
final class StreamWrapperDriverTest extends TestCase
{
    protected function tearDown(): void
    {
        AopStreamWrapper::unregister();
    }

    public function testIsASourceTransformingDriver(): void
    {
        // The kernel registers the transformer chain and the cache warmer accepts the driver by this interface
        $this->assertContains(SourceTransformingDriver::class, class_implements(StreamWrapperDriver::class));
    }

    public function testRefusesIncludeInterception(): void
    {
        try {
            new StreamWrapperDriver()->boot($this->kernel(Features::INTERCEPT_INCLUDES), $this->createStub(AspectContainer::class));
            self::fail('Includes are intercepted by the wrapper itself, the flag must be refused');
        } catch (StreamWrapperConfigurationException $refusal) {
            $this->assertStringContainsString('Features::INTERCEPT_INCLUDES', $refusal->getMessage());
            $this->assertStringContainsString('Drop the flag', $refusal->getMessage());
        }
        $this->assertFalse(AopStreamWrapper::isRegistered(), 'Nothing is registered on a refusal');
    }

    public function testBootReplacesTheFileWrapper(): void
    {
        new StreamWrapperDriver()->boot($this->kernel(0), $this->createStub(AspectContainer::class));

        $this->assertTrue(AopStreamWrapper::isRegistered());
        $this->assertContains('file', stream_get_wrappers());
        $this->assertFileExists(__FILE__, 'Plain file operations are delegated to the native wrapper');

        AopStreamWrapper::unregister();
        $this->assertFalse(AopStreamWrapper::isRegistered());
        $this->assertContains('file', stream_get_wrappers());
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
            'excludePaths'   => [__DIR__ . '/cache'],
            'containerClass' => Container::class,
            'driver'         => StreamWrapperDriver::class,
        ]);

        return $kernel;
    }
}
