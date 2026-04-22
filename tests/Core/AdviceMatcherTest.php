<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Core;

use Go\Aop\Advice;
use Go\Aop\Pointcut;
use Go\Aop\Pointcut\TruePointcut;
use Go\Aop\Support\GenericPointcutAdvisor;
use Go\ParserReflection\Locator\ComposerLocator;
use Go\ParserReflection\ReflectionEngine;
use Go\ParserReflection\ReflectionFile;
use Go\Stubs\First;
use Go\Stubs\PropertyHookSupport;
use Go\Stubs\PropertyHookSupportPromoted;
use Go\Stubs\PropertyInheritanceChild;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class AdviceMatcherTest extends TestCase
{
    protected AdviceMatcherInterface $adviceMatcher;

    protected ReflectionClass $reflectionClass;

    /**
     * @inheritDoc
     */
    public static function setUpBeforeClass(): void
    {
        ReflectionEngine::init(new ComposerLocator());
    }

    protected function setUp(): void
    {
        $this->adviceMatcher = new AdviceMatcher();

        $reflectionFile        = new ReflectionFile(__FILE__);
        $this->reflectionClass = $reflectionFile
            ->getFileNamespace(__NAMESPACE__)
            ->getClass(self::class)
        ;
    }

    /**
     * Verifies that empty result will be returned without aspects and advisors
     */
    public function testGetEmptyAdvicesForClass(): void
    {
        // by reflection
        $advices = $this->adviceMatcher->getAdvicesForClass($this->reflectionClass, []);
        $this->assertEmpty($advices);
    }

    /**
     * Check that list of advices for method works correctly
     */
    public function testGetSingleMethodAdviceForClassFromAdvisor(): void
    {
        $methodName = __FUNCTION__;

        $pointcut = $this->createMock(Pointcut::class);
        $pointcut
            ->method('matches')
            ->willReturnCallback(
                function (ReflectionClass $class, ReflectionMethod|null $method) use ($methodName): bool {
                    return !isset($method) || $method->name === $methodName;
                }
            )
        ;
        $pointcut
            ->method('getKind')
            ->willReturn(Pointcut::KIND_METHOD)
        ;

        $advice  = $this->createMock(Advice::class);
        $advisor = new GenericPointcutAdvisor($pointcut, $advice);

        $advices = $this->adviceMatcher->getAdvicesForClass($this->reflectionClass, ['advisor' => $advisor]);
        $this->assertArrayHasKey(AspectContainer::METHOD_PREFIX, $advices);
        $this->assertArrayHasKey($methodName, $advices[AspectContainer::METHOD_PREFIX]);
        $this->assertCount(1, $advices[AspectContainer::METHOD_PREFIX]);
    }

    /**
     * Verifies that private instance methods are now matched by AdviceMatcher.
     * Previously private methods were silently excluded (IS_PUBLIC|IS_PROTECTED mask).
     * With the trait-based proxy engine they can be intercepted, so the mask now includes IS_PRIVATE.
     */
    public function testPrivateMethodIsMatchedByAdviceMatcher(): void
    {
        $reflectionClass = new ReflectionClass(First::class);
        $methodName      = 'privateMethod'; // private function privateMethod(): int

        $pointcut = $this->createMock(Pointcut::class);
        $pointcut->method('getKind')->willReturn(Pointcut::KIND_METHOD);
        $pointcut->method('matches')->willReturnCallback(
            static fn(ReflectionClass $c, ?ReflectionMethod $m = null): bool =>
                $m === null || $m->name === $methodName
        );

        $advice  = $this->createMock(Advice::class);
        $advisor = new GenericPointcutAdvisor($pointcut, $advice);

        $advices = $this->adviceMatcher->getAdvicesForClass($reflectionClass, ['advisor' => $advisor]);

        $this->assertArrayHasKey(AspectContainer::METHOD_PREFIX, $advices);
        $this->assertArrayHasKey($methodName, $advices[AspectContainer::METHOD_PREFIX]);
    }

    /**
     * Verifies that private static methods are also matched by AdviceMatcher.
     */
    public function testPrivateStaticMethodIsMatchedByAdviceMatcher(): void
    {
        $reflectionClass = new ReflectionClass(First::class);
        $methodName      = 'staticSelfPrivate'; // private static function staticSelfPrivate(): int

        $pointcut = $this->createMock(Pointcut::class);
        $pointcut->method('getKind')->willReturn(Pointcut::KIND_METHOD);
        $pointcut->method('matches')->willReturnCallback(
            static fn(ReflectionClass $c, ?ReflectionMethod $m = null): bool =>
                $m === null || $m->name === $methodName
        );

        $advice  = $this->createMock(Advice::class);
        $advisor = new GenericPointcutAdvisor($pointcut, $advice);

        $advices = $this->adviceMatcher->getAdvicesForClass($reflectionClass, ['advisor' => $advisor]);

        $this->assertArrayHasKey(AspectContainer::STATIC_METHOD_PREFIX, $advices);
        $this->assertArrayHasKey($methodName, $advices[AspectContainer::STATIC_METHOD_PREFIX]);
    }

    /**
     * Verifies that private methods inherited from a parent class are NOT matched —
     * they cannot be intercepted because they live in the parent's scope, not the trait.
     */
    public function testPrivateMethodFromParentClassIsNotMatched(): void
    {
        // Create an anonymous class that extends First — First::privateMethod is inherited but not overridable
        $child           = new class extends First {};
        $reflectionClass = new ReflectionClass($child);

        $pointcut = $this->createMock(Pointcut::class);
        $pointcut->method('getKind')->willReturn(Pointcut::KIND_METHOD);
        $pointcut->method('matches')->willReturn(true); // match everything

        $advice  = $this->createMock(Advice::class);
        $advisor = new GenericPointcutAdvisor($pointcut, $advice);

        $advices = $this->adviceMatcher->getAdvicesForClass($reflectionClass, ['advisor' => $advisor]);

        // privateMethod and staticSelfPrivate live in First, not in the anonymous child — must not appear
        $methodAdvices = $advices[AspectContainer::METHOD_PREFIX] ?? [];
        $this->assertArrayNotHasKey('privateMethod', $methodAdvices);
        $staticAdvices = $advices[AspectContainer::STATIC_METHOD_PREFIX] ?? [];
        $this->assertArrayNotHasKey('staticSelfPrivate', $staticAdvices);
    }

    /**
     * Check that list of advices for fields works correctly
     */
    public function testGetSinglePropertyAdviceForClassFromAdvisor(): void
    {
        $propertyName = 'adviceMatcher'; // $this->adviceMatcher;

        $pointcut = $this->createMock(Pointcut::class);
        $pointcut
            ->method('matches')
            ->willReturnCallback(
                function (ReflectionClass $class, ReflectionProperty|null $property) use ($propertyName): bool {
                    return !isset($property) || $property->name === $propertyName;
                }
            )
        ;
        $pointcut
            ->method('getKind')
            ->willReturn(Pointcut::KIND_PROPERTY)
        ;

        $advice  = $this->createMock(Advice::class);
        $advisor = new GenericPointcutAdvisor($pointcut, $advice);

        $advices = $this->adviceMatcher->getAdvicesForClass($this->reflectionClass, ['advisor' => $advisor]);
        $this->assertArrayHasKey(AspectContainer::PROPERTY_PREFIX, $advices);
        $this->assertArrayHasKey($propertyName, $advices[AspectContainer::PROPERTY_PREFIX]);
        $this->assertCount(1, $advices[AspectContainer::PROPERTY_PREFIX]);
    }

    public function testReadonlyAndHookedPropertiesAreSkippedForInterception(): void
    {
        $reflectionClass = new ReflectionClass(PropertyHookSupport::class);

        $pointcut = $this->createMock(Pointcut::class);
        $pointcut->method('getKind')->willReturn(Pointcut::KIND_PROPERTY);
        $pointcut->method('matches')->willReturn(true);

        $advice  = $this->createMock(Advice::class);
        $advisor = new GenericPointcutAdvisor($pointcut, $advice);

        $advices = $this->adviceMatcher->getAdvicesForClass($reflectionClass, ['advisor' => $advisor]);
        $propertyAdvices = $advices[AspectContainer::PROPERTY_PREFIX] ?? [];

        $this->assertArrayHasKey('intercepted', $propertyAdvices);
        $this->assertArrayNotHasKey('readonly', $propertyAdvices);
        $this->assertArrayNotHasKey('alreadyHooked', $propertyAdvices);
    }

    public function testPromotedReadonlyAndHookedPropertiesAreSkippedForInterception(): void
    {
        $reflectionClass = new ReflectionClass(PropertyHookSupportPromoted::class);

        $pointcut = $this->createMock(Pointcut::class);
        $pointcut->method('getKind')->willReturn(Pointcut::KIND_PROPERTY);
        $pointcut->method('matches')->willReturn(true);

        $advice  = $this->createMock(Advice::class);
        $advisor = new GenericPointcutAdvisor($pointcut, $advice);

        $advices = $this->adviceMatcher->getAdvicesForClass($reflectionClass, ['advisor' => $advisor]);
        $propertyAdvices = $advices[AspectContainer::PROPERTY_PREFIX] ?? [];

        $this->assertArrayHasKey('promoted', $propertyAdvices);
        $this->assertArrayNotHasKey('readonlyPromoted', $propertyAdvices);
        $this->assertArrayNotHasKey('hookedPromoted', $propertyAdvices);
    }

    public function testParentPublicAndProtectedPropertiesAreMatchedForInterception(): void
    {
        $reflectionClass = new ReflectionClass(PropertyInheritanceChild::class);

        $pointcut = $this->createMock(Pointcut::class);
        $pointcut->method('getKind')->willReturn(Pointcut::KIND_PROPERTY);
        $pointcut->method('matches')->willReturn(true);

        $advice  = $this->createMock(Advice::class);
        $advisor = new GenericPointcutAdvisor($pointcut, $advice);

        $advices = $this->adviceMatcher->getAdvicesForClass($reflectionClass, ['advisor' => $advisor]);
        $propertyAdvices = $advices[AspectContainer::PROPERTY_PREFIX] ?? [];

        $this->assertArrayHasKey('parentPublic', $propertyAdvices);
        $this->assertArrayHasKey('parentProtected', $propertyAdvices);
        $this->assertArrayHasKey('childPublic', $propertyAdvices);
        $this->assertArrayHasKey('childFinal', $propertyAdvices);
    }

    public function testParentFinalPropertyIsSkippedForInterception(): void
    {
        $reflectionClass = new ReflectionClass(PropertyInheritanceChild::class);

        $pointcut = $this->createMock(Pointcut::class);
        $pointcut->method('getKind')->willReturn(Pointcut::KIND_PROPERTY);
        $pointcut->method('matches')->willReturn(true);

        $advice  = $this->createMock(Advice::class);
        $advisor = new GenericPointcutAdvisor($pointcut, $advice);

        $advices = $this->adviceMatcher->getAdvicesForClass($reflectionClass, ['advisor' => $advisor]);
        $propertyAdvices = $advices[AspectContainer::PROPERTY_PREFIX] ?? [];

        $this->assertArrayNotHasKey('parentFinal', $propertyAdvices);
    }

    public function testFinalPropertyInCurrentClassIsMatchedForInterception(): void
    {
        $reflectionClass = new ReflectionClass(PropertyInheritanceChild::class);

        $pointcut = $this->createMock(Pointcut::class);
        $pointcut->method('getKind')->willReturn(Pointcut::KIND_PROPERTY);
        $pointcut->method('matches')->willReturn(true);

        $advice  = $this->createMock(Advice::class);
        $advisor = new GenericPointcutAdvisor($pointcut, $advice);

        $advices = $this->adviceMatcher->getAdvicesForClass($reflectionClass, ['advisor' => $advisor]);
        $propertyAdvices = $advices[AspectContainer::PROPERTY_PREFIX] ?? [];

        $this->assertArrayHasKey('childFinal', $propertyAdvices);
    }
}
