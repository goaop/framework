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
        $invocation = new DynamicTraitAliasMethodInvocation([], TraitAliasProxy::class, $methodName, $instance->getCallableFor($methodName));

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
        $invocation = new DynamicTraitAliasMethodInvocation([], TraitAliasProxy::class, 'privateMethod', $instance->getCallableFor('privateMethod'));

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
        $invocation = new DynamicTraitAliasMethodInvocation([$advice], TraitAliasProxy::class, 'publicMethod', $instance->getCallableFor('publicMethod'));

        $result = $invocation($instance);
        $this->assertTrue($called);
        $this->assertSame(T_PUBLIC, $result);
    }

    public function testVariadicArgumentsAreForwarded(): void
    {
        $instance   = new TraitAliasProxy();
        $invocation = new DynamicTraitAliasMethodInvocation([], TraitAliasProxy::class, 'variadicArgsTest', $instance->getCallableFor('variadicArgsTest'));

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
        $invocation = new DynamicTraitAliasMethodInvocation([], TraitAliasProxy::class, 'passByReference', $instance->getCallableFor('passByReference'));

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
        $invocation = new DynamicTraitAliasMethodInvocation([$advice], TraitAliasProxy::class, 'publicMethod', $instance->getCallableFor('publicMethod'));
        $invocation($instance);
        unset($GLOBALS['__test_instance']);
    }

    public function testIsDynamicReturnsTrue(): void
    {
        $instance   = new TraitAliasProxy();
        $invocation = new DynamicTraitAliasMethodInvocation([], TraitAliasProxy::class, 'publicMethod', $instance->getCallableFor('publicMethod'));
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

        $invocation = new DynamicTraitAliasMethodInvocation([$advice], TraitAliasProxy::class, 'publicMethod', $instance->getCallableFor('publicMethod'));
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

    /**
     * An inherited method that has no trait alias uses the parent:: first-class callable
     * (generated by ClassProxyGenerator). DynamicTraitAliasMethodInvocation resolves the
     * prototype via reflection and calls it directly via ReflectionMethod::invokeArgs.
     */
    public function testInheritedMethodInvocationWithParentCallable(): void
    {
        $instance   = new InheritedMethodProxy();
        $callable   = $instance->getInheritedCallable('inheritedPublicMethod');
        $invocation = new DynamicTraitAliasMethodInvocation([], InheritedMethodProxy::class, 'inheritedPublicMethod', $callable);

        $result = $invocation($instance);
        $this->assertSame(T_PUBLIC, $result);
    }

    // --- ReflectionMethod-based dispatch path ---

    /**
     * When the joinpoint is invoked with multiple instances in sequence, ReflectionMethod::invokeArgs
     * must correctly dispatch to each instance (no $this capture issue, since we use reflection).
     */
    public function testReflectionDispatchCallsEachInstanceCorrectly(): void
    {
        $first  = new TraitAliasProxy();
        $second = new TraitAliasProxy();

        $callable   = $first->createGetObjectIdCallable();
        $invocation = new DynamicTraitAliasMethodInvocation([], TraitAliasProxy::class, 'getObjectId', $callable);

        $resultFirst  = $invocation($first);
        $resultSecond = $invocation($second);

        // Each call must return the spl_object_id of the respective instance.
        $this->assertSame(spl_object_id($first),  $resultFirst);
        $this->assertSame(spl_object_id($second), $resultSecond);
        $this->assertNotSame($resultFirst, $resultSecond, 'Different instances must produce different object IDs');
    }

    /**
     * The dispatch must route through the original method body (the __aop__ alias), not through
     * the overridden public method that returns the sentinel -1.
     */
    public function testDispatchInvokesOriginalMethodBody(): void
    {
        $instance = new TraitAliasProxy();

        $callable   = $instance->createGetObjectIdCallable();
        $invocation = new DynamicTraitAliasMethodInvocation([], TraitAliasProxy::class, 'getObjectId', $callable);

        $result = $invocation($instance);
        $this->assertSame(spl_object_id($instance), $result);
    }

    /**
     * For inherited methods, ReflectionMethod::invokeArgs must correctly dispatch to each
     * instance, using the prototype method from the parent class.
     */
    public function testInheritedMethodDispatchCallsEachInstanceCorrectly(): void
    {
        $first  = new InheritedMethodProxy();
        $second = new InheritedMethodProxy();

        $callable   = $first->getInheritedCallable('inheritedPublicMethod');
        $invocation = new DynamicTraitAliasMethodInvocation([], InheritedMethodProxy::class, 'inheritedPublicMethod', $callable);

        $resultFirst  = $invocation($first);
        $resultSecond = $invocation($second);

        // Both instances should return T_PUBLIC (the parent method body returns T_PUBLIC)
        $this->assertSame(T_PUBLIC, $resultFirst);
        $this->assertSame(T_PUBLIC, $resultSecond);
    }
}
