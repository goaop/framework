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

use Go\Aop\Intercept\Interceptor as InterceptorContract;
use Go\Stubs\TraitAliasProxy;
use PHPUnit\Framework\TestCase;

class InterceptorInjectorTest extends TestCase
{
    protected string $classProperty;

    protected ?string $sharedProperty = null;

    /**
     * @return non-empty-list<InterceptorContract>
     */
    private function noopInterceptors(): array
    {
        return [Interceptor::before(static fn() => null)];
    }

    public function testForMethodBuildsDynamicTraitAliasMethodInvocation(): void
    {
        $instance   = new TraitAliasProxy();
        $callable   = $instance->getCallableFor('publicMethod');
        $invocation = InterceptorInjector::forMethod(TraitAliasProxy::class, 'publicMethod', $this->noopInterceptors(), $callable);

        $this->assertInstanceOf(DynamicTraitAliasMethodInvocation::class, $invocation);
        $result = $invocation($instance);
        $this->assertSame(T_PUBLIC, $result);
    }

    public function testForStaticMethodBuildsStaticTraitAliasMethodInvocation(): void
    {
        $callable   = TraitAliasProxy::getStaticCallableFor('staticPublicMethod');
        $invocation = InterceptorInjector::forStaticMethod(TraitAliasProxy::class, 'staticPublicMethod', $this->noopInterceptors(), $callable);

        $this->assertInstanceOf(StaticTraitAliasMethodInvocation::class, $invocation);
        $result = $invocation(TraitAliasProxy::class);
        $this->assertSame(TraitAliasProxy::class, $result);
    }

    public function testForPropertyBuildsClassFieldAccess(): void
    {
        $fieldAccess = InterceptorInjector::forProperty(self::class, 'classProperty', $this->noopInterceptors());

        $this->assertInstanceOf(ClassFieldAccess::class, $fieldAccess);
        $this->assertSame('classProperty', $fieldAccess->getField()->name);

        $this->assertSame('hello', $fieldAccess->read($this, 'hello'));
    }

    /**
     * The get and set hooks of a property share its joinpoint (#819)
     */
    public function testForPropertyReturnsOneJoinpointPerClassAndProperty(): void
    {
        $getHookJoinpoint = InterceptorInjector::forProperty(self::class, 'sharedProperty', $this->noopInterceptors());
        $setHookJoinpoint = InterceptorInjector::forProperty(self::class, 'sharedProperty', $this->noopInterceptors());

        $this->assertSame($getHookJoinpoint, $setHookJoinpoint);
        $this->assertNotSame($getHookJoinpoint, InterceptorInjector::forProperty(self::class, 'classProperty', $this->noopInterceptors()));
        // Another class with a property of the same name has its own joinpoint
        $this->assertNotSame(
            spl_object_id($getHookJoinpoint),
            spl_object_id(InterceptorInjector::forProperty(TraitAliasProxy::class, 'sharedProperty', $this->noopInterceptors())),
        );
    }

    public function testForFunctionBuildsReflectionFunctionInvocation(): void
    {
        $invocation = InterceptorInjector::forFunction('strlen', $this->noopInterceptors(), \strlen(...));

        $this->assertInstanceOf(ReflectionFunctionInvocation::class, $invocation);
        $this->assertSame(5, $invocation(['hello']));
    }

    public function testForStaticInitializationBuildsStaticInitializationJoinpoint(): void
    {
        $called = false;
        $interceptors = [Interceptor::before(static function () use (&$called): void {
            $called = true;
        })];
        $joinPoint = InterceptorInjector::forStaticInitialization(self::class, $interceptors);

        $this->assertInstanceOf(StaticInitializationJoinpoint::class, $joinPoint);
        $joinPoint();
        $this->assertTrue($called);
    }

    public function testForInitializationBuildsReflectionConstructorInvocation(): void
    {
        $invocation = InterceptorInjector::forInitialization(self::class, $this->noopInterceptors());

        $this->assertInstanceOf(ReflectionConstructorInvocation::class, $invocation);
        $this->assertSame(self::class, $invocation->getScope());
    }
}
