<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Go\Aop\Intercept\FieldAccessType;
use Go\Stubs\TraitAliasProxy;
use PHPUnit\Framework\TestCase;

class InterceptorInjectorTest extends TestCase
{
    protected string $classProperty;

    public function testForMethodBuildsDynamicTraitAliasMethodInvocation(): void
    {
        $instance   = new TraitAliasProxy();
        $callable   = $instance->getCallableFor('publicMethod');
        $invocation = InterceptorInjector::forMethod(TraitAliasProxy::class, 'publicMethod', [], $callable);

        $this->assertInstanceOf(DynamicTraitAliasMethodInvocation::class, $invocation);
        $result = $invocation($instance);
        $this->assertSame(T_PUBLIC, $result);
    }

    public function testForStaticMethodBuildsStaticTraitAliasMethodInvocation(): void
    {
        $callable   = TraitAliasProxy::getStaticCallableFor('staticPublicMethod');
        $invocation = InterceptorInjector::forStaticMethod(TraitAliasProxy::class, 'staticPublicMethod', [], $callable);

        $this->assertInstanceOf(StaticTraitAliasMethodInvocation::class, $invocation);
        $result = $invocation(TraitAliasProxy::class);
        $this->assertSame(TraitAliasProxy::class, $result);
    }

    public function testForPropertyBuildsClassFieldAccess(): void
    {
        $fieldAccess = InterceptorInjector::forProperty(self::class, 'classProperty', []);

        $this->assertInstanceOf(ClassFieldAccess::class, $fieldAccess);
        $this->assertSame('classProperty', $fieldAccess->getField()->name);

        $value = 'hello';
        $result = $fieldAccess->__invoke($this, FieldAccessType::READ, $value);
        $this->assertSame('hello', $result);
    }

    public function testForFunctionBuildsReflectionFunctionInvocation(): void
    {
        $invocation = InterceptorInjector::forFunction('strlen', [], \strlen(...));

        $this->assertInstanceOf(ReflectionFunctionInvocation::class, $invocation);
        $this->assertSame(5, $invocation(['hello']));
    }

    public function testForStaticInitializationBuildsStaticInitializationJoinpoint(): void
    {
        $joinPoint = InterceptorInjector::forStaticInitialization(self::class, []);

        $this->assertInstanceOf(StaticInitializationJoinpoint::class, $joinPoint);
        $this->assertNull($joinPoint());
    }

    public function testForInitializationBuildsReflectionConstructorInvocation(): void
    {
        $invocation = InterceptorInjector::forInitialization(self::class, []);

        $this->assertInstanceOf(ReflectionConstructorInvocation::class, $invocation);
        $this->assertSame(self::class, $invocation->getScope());
    }
}
