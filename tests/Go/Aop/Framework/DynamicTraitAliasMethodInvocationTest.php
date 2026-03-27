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

class DynamicTraitAliasMethodInvocationTest extends TestCase
{
    /**
     * Verifies that invoking routes through __aop__<method> (the trait alias),
     * not through the overridden public method that returns the sentinel -1.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dynamicMethodsBatch')]
    public function testDynamicMethodInvocation(string $methodName, int $expectedResult): void
    {
        $instance   = new TraitAliasProxy();
        $invocation = new DynamicTraitAliasMethodInvocation([], TraitAliasProxy::class, $methodName);

        $result = $invocation($instance);
        $this->assertSame($expectedResult, $result);
    }

    /**
     * Private method interception is the key advantage of the trait-alias engine over the legacy
     * extend-based approach. Verify it works end-to-end.
     */
    public function testPrivateMethodCanBeIntercepted(): void
    {
        $instance   = new TraitAliasProxy();
        $invocation = new DynamicTraitAliasMethodInvocation([], TraitAliasProxy::class, 'privateMethod');

        $result = $invocation($instance);
        $this->assertSame(T_PRIVATE, $result);
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

        $instance   = new TraitAliasProxy();
        $invocation = new DynamicTraitAliasMethodInvocation([$advice], TraitAliasProxy::class, 'publicMethod');

        $result = $invocation($instance);
        $this->assertTrue($called);
        $this->assertSame(T_PUBLIC, $result);
    }

    public function testVariadicArgumentsAreForwarded(): void
    {
        $instance   = new TraitAliasProxy();
        $invocation = new DynamicTraitAliasMethodInvocation([], TraitAliasProxy::class, 'variadicArgsTest');

        $args     = [];
        $expected = '';
        for ($i = 0; $i < 5; $i++) {
            $args[]   = $i;
            $expected .= $i;
            $result   = $invocation($instance, $args);
            $this->assertSame($expected, $result);
        }
    }

    public function testPassByReferenceIsForwarded(): void
    {
        $instance   = new TraitAliasProxy();
        $invocation = new DynamicTraitAliasMethodInvocation([], TraitAliasProxy::class, 'passByReference');

        $value  = 'original';
        $result = $invocation($instance, [&$value]);
        $this->assertNull($result);
        $this->assertNull($value);
    }

    public function testGetThisReturnsPassedInstance(): void
    {
        $instance   = new TraitAliasProxy();
        $advice     = $this->createMock(Interceptor::class);
        $advice->expects($this->once())
            ->method('invoke')
            ->willReturnCallback(function (MethodInvocation $inv): mixed {
                $this->assertSame($GLOBALS['__test_instance'], $inv->getThis());

                return $inv->proceed();
            });

        $GLOBALS['__test_instance'] = $instance;
        $invocation = new DynamicTraitAliasMethodInvocation([$advice], TraitAliasProxy::class, 'publicMethod');
        $invocation($instance);
        unset($GLOBALS['__test_instance']);
    }

    public function testIsDynamicReturnsTrue(): void
    {
        $invocation = new DynamicTraitAliasMethodInvocation([], TraitAliasProxy::class, 'publicMethod');
        $this->assertTrue($invocation->isDynamic());
    }

    public function testGetScopeReturnsProxyClassName(): void
    {
        $instance   = new TraitAliasProxy();
        $advice     = $this->createMock(Interceptor::class);
        $advice->expects($this->once())
            ->method('invoke')
            ->willReturnCallback(function (MethodInvocation $inv): mixed {
                $this->assertSame(TraitAliasProxy::class, $inv->getScope());

                return $inv->proceed();
            });

        $invocation = new DynamicTraitAliasMethodInvocation([$advice], TraitAliasProxy::class, 'publicMethod');
        $invocation($instance);
    }

    public static function dynamicMethodsBatch(): array
    {
        return [
            // publicMethod is overridden in TraitAliasProxy to return -1;
            // the invocation must go through __aop__publicMethod which returns T_PUBLIC.
            ['publicMethod',    T_PUBLIC],
            ['protectedMethod', T_PROTECTED],
        ];
    }
}
