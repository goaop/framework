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
use Go\Stubs\InheritedMethodProxy;
use Go\Stubs\InheritedMethodProxyChild;
use Go\Stubs\TraitAliasProxy;
use Go\Stubs\TraitAliasProxyChild;
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

    /**
     * Regression: when a subclass of the proxy is used as the static scope (LSB — e.g. via
     * static::class in the generated override method), the joinpoint must still route through
     * the private __aop__ alias defined on the parent proxy class and execute the original
     * method body correctly.
     */
    public function testLateStaticBindingWithSubclassScope(): void
    {
        $invocation = new StaticTraitAliasMethodInvocation([], TraitAliasProxy::class, 'staticPublicMethod');

        // Passing the subclass as scope simulates `static::class` returning a child class
        $result = $invocation(TraitAliasProxyChild::class);
        $this->assertSame(T_PUBLIC, $result);
    }

    public function testGetScopeReturnsSubclassWhenCalledWithSubclassScope(): void
    {
        $advice = $this->createMock(Interceptor::class);
        $advice->expects($this->once())
            ->method('invoke')
            ->willReturnCallback(function (MethodInvocation $inv): mixed {
                $this->assertSame(TraitAliasProxyChild::class, $inv->getScope());

                return $inv->proceed();
            });

        $invocation = new StaticTraitAliasMethodInvocation([$advice], TraitAliasProxy::class, 'staticPublicMethod');
        $invocation(TraitAliasProxyChild::class);
    }

    public function testInheritedStaticMethodInvocationWithoutTraitAliasUsesParentMethod(): void
    {
        $invocation = new StaticTraitAliasMethodInvocation([], InheritedMethodProxy::class, 'inheritedStaticMethod');

        $result = $invocation(InheritedMethodProxy::class);
        $this->assertSame(T_PUBLIC, $result);
    }

    public function testInheritedStaticMethodInvocationWithLsbUsesChildClassScope(): void
    {
        $invocation = new StaticTraitAliasMethodInvocation([], InheritedMethodProxy::class, 'inheritedStaticLsbMethod');

        $result = $invocation(InheritedMethodProxyChild::class);
        $this->assertSame([InheritedMethodProxyChild::class, InheritedMethodProxyChild::class], $result);
    }
}
