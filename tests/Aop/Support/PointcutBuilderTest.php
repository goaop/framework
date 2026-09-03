<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Support;

use Go\Aop\Framework\AfterInterceptor;
use Go\Aop\Framework\AfterThrowingInterceptor;
use Go\Aop\Framework\AroundInterceptor;
use Go\Aop\Framework\BeforeInterceptor;
use Go\Core\Container;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class PointcutBuilderTest extends TestCase
{
    private Container $container;

    private PointcutBuilder $builder;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->builder    = new PointcutBuilder($this->container);
    }

    public function testBeforeRegistersLazyPointcutAdvisorWrappingBeforeInterceptor(): void
    {
        $advice = static function (): void {};

        $this->builder->before('execution(public Foo->bar(*))', $advice);

        $advisor = $this->findRegisteredAdvisor('execution(public Foo->bar(*))');
        $this->assertInstanceOf(LazyPointcutAdvisor::class, $advisor);
        $this->assertInstanceOf(BeforeInterceptor::class, $advisor->getAdvice());
    }

    public function testAfterRegistersLazyPointcutAdvisorWrappingAfterInterceptor(): void
    {
        $advice = static function (): void {};

        $this->builder->after('execution(public Foo->bar(*))', $advice);

        $advisor = $this->findRegisteredAdvisor('execution(public Foo->bar(*))');
        $this->assertInstanceOf(LazyPointcutAdvisor::class, $advisor);
        $this->assertInstanceOf(AfterInterceptor::class, $advisor->getAdvice());
    }

    public function testAfterThrowingRegistersLazyPointcutAdvisorWrappingAfterThrowingInterceptor(): void
    {
        $advice = static function (): void {};

        $this->builder->afterThrowing('execution(public Foo->bar(*))', $advice);

        $advisor = $this->findRegisteredAdvisor('execution(public Foo->bar(*))');
        $this->assertInstanceOf(LazyPointcutAdvisor::class, $advisor);
        $this->assertInstanceOf(AfterThrowingInterceptor::class, $advisor->getAdvice());
    }

    public function testAroundRegistersLazyPointcutAdvisorWrappingAroundInterceptor(): void
    {
        $advice = static function (): void {};

        $this->builder->around('execution(public Foo->bar(*))', $advice);

        $advisor = $this->findRegisteredAdvisor('execution(public Foo->bar(*))');
        $this->assertInstanceOf(LazyPointcutAdvisor::class, $advisor);
        $this->assertInstanceOf(AroundInterceptor::class, $advisor->getAdvice());
    }

    public function testEachRegistrationGetsAUniqueContainerId(): void
    {
        $advice = static function (): void {};

        $this->builder->before('execution(public Foo->bar(*))', $advice);
        $this->builder->before('execution(public Foo->bar(*))', $advice);

        $values = $this->readContainerValues();
        $matchingKeys = array_filter(
            array_keys($values),
            static fn(string $key): bool => str_starts_with($key, 'execution_public_Foo_bar_'),
        );

        $this->assertCount(2, $matchingKeys);
        $this->assertNotSame(...array_values($matchingKeys));
    }

    /**
     * @return array<array-key, mixed>
     */
    private function readContainerValues(): array
    {
        $property = new ReflectionProperty(Container::class, 'values');
        $values   = $property->getValue($this->container);

        if (!is_array($values)) {
            throw new \RuntimeException('Expected Container::$values to be an array');
        }

        return $values;
    }

    private function findRegisteredAdvisor(string $pointcutExpression): mixed
    {
        $prefix = (preg_replace('/\W+/', '_', $pointcutExpression) ?? '') . '.';
        foreach ($this->readContainerValues() as $id => $value) {
            if (str_starts_with((string) $id, $prefix)) {
                return $value;
            }
        }

        $this->fail("No registered advisor found for prefix {$prefix}");
    }
}
