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

use Go\Aop\Intercept\Interceptor;
use Go\Aop\Intercept\MethodInvocation;
use Go\Stubs\TraitAliasProxy;
use PHPUnit\Framework\TestCase;

class StaticTraitAliasMethodInvocationTest extends TestCase
{
    /**
     * Verifies that invoking routes through __aop__<method> (the trait alias),
     * not through the overridden static method that returns the sentinel -1.
     */
    public function testStaticMethodInvocation(): void
    {
        $invocation = new StaticTraitAliasMethodInvocation([], TraitAliasProxy::class, 'staticPublicMethod');

        $result = $invocation(TraitAliasProxy::class);
        $this->assertSame(T_PUBLIC, $result);
    }

    public function testAdviceIsCalledBeforeProceeding(): void
    {
        $called = false;
        $advice = $this->createMock(Interceptor::class);
        $advice->expects($this->once())
            ->method('invoke')
            ->willReturnCallback(function (MethodInvocation $inv) use (&$called): mixed {
                $called = true;

                return $inv->proceed();
            });

        $invocation = new StaticTraitAliasMethodInvocation([$advice], TraitAliasProxy::class, 'staticPublicMethod');

        $result = $invocation(TraitAliasProxy::class);
        $this->assertTrue($called);
        $this->assertSame(T_PUBLIC, $result);
    }

    public function testVariadicArgumentsAreForwarded(): void
    {
        $invocation = new StaticTraitAliasMethodInvocation([], TraitAliasProxy::class, 'staticVariadicArgsTest');

        $args     = [];
        $expected = '';
        for ($i = 0; $i < 5; $i++) {
            $args[]   = $i;
            $expected .= $i;
            $result   = $invocation(TraitAliasProxy::class, $args);
            $this->assertSame($expected, $result);
        }
    }

    public function testGetThisReturnsNull(): void
    {
        $advice = $this->createMock(Interceptor::class);
        $advice->expects($this->once())
            ->method('invoke')
            ->willReturnCallback(function (MethodInvocation $inv): mixed {
                $this->assertNull($inv->getThis());

                return $inv->proceed();
            });

        $invocation = new StaticTraitAliasMethodInvocation([$advice], TraitAliasProxy::class, 'staticPublicMethod');
        $invocation(TraitAliasProxy::class);
    }

    public function testIsDynamicReturnsFalse(): void
    {
        $invocation = new StaticTraitAliasMethodInvocation([], TraitAliasProxy::class, 'staticPublicMethod');
        $this->assertFalse($invocation->isDynamic());
    }

    public function testGetScopeReturnsProxyClassName(): void
    {
        $advice = $this->createMock(Interceptor::class);
        $advice->expects($this->once())
            ->method('invoke')
            ->willReturnCallback(function (MethodInvocation $inv): mixed {
                $this->assertSame(TraitAliasProxy::class, $inv->getScope());

                return $inv->proceed();
            });

        $invocation = new StaticTraitAliasMethodInvocation([$advice], TraitAliasProxy::class, 'staticPublicMethod');
        $invocation(TraitAliasProxy::class);
    }
}
