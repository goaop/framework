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

namespace Go\Aop\Framework;

use Go\Aop\Intercept\Joinpoint;
use PHPUnit\Framework\TestCase;

class DeclareErrorInterceptorTest extends TestCase
{
    public function testCanSerializeAndUnserialize(): void
    {
        $interceptor = new DeclareErrorInterceptor('Deprecated method', E_USER_DEPRECATED, 'execution(Some::method)');

        $serialized   = serialize($interceptor);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(DeclareErrorInterceptor::class, $unserialized);
    }

    public function testUnserializedInterceptorPreservesMessageAndLevel(): void
    {
        $interceptor = new DeclareErrorInterceptor('Deprecated method', E_USER_DEPRECATED, 'execution(Some::method)');

        /** @var DeclareErrorInterceptor $unserialized */
        $unserialized = unserialize(serialize($interceptor));

        $joinpoint = $this->createMock(Joinpoint::class);
        $joinpoint->expects($this->once())->method('proceed')->willReturn(null);

        $capturedMessage = null;
        $capturedLevel   = null;
        set_error_handler(function (int $level, string $message) use (&$capturedMessage, &$capturedLevel): bool {
            $capturedLevel   = $level;
            $capturedMessage = $message;
            return true;
        });
        $unserialized->invoke($joinpoint);
        restore_error_handler();

        $this->assertSame(E_USER_DEPRECATED, $capturedLevel);
        $this->assertStringContainsString('Deprecated method', $capturedMessage);
    }
}
