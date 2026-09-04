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
use Go\Aop\Framework\TraitIntroductionInfo;
use Go\Aop\IntroductionInfo;
use Go\Aop\Pointcut;
use Go\Aop\Pointcut\TruePointcut;
use Go\Aop\Support\GenericPointcutAdvisor;
use Go\ParserReflection\Locator\ComposerLocator;
use Go\ParserReflection\ReflectionEngine;
use Go\ParserReflection\ReflectionFile;
use Go\ParserReflection\ReflectionFileNamespace;
use Go\Stubs\First;
use Go\Stubs\PropertyHookSupport;
use Go\Stubs\PropertyHookSupportPromoted;
use Go\Stubs\PropertyInheritanceChild;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionProperty;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class AdviceMatcherTest extends TestCase
{
    protected AdviceMatcherInterface $adviceMatcher;

    /** @var ReflectionClass<object> */
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
                function (ReflectionClass $class, ?ReflectionMethod $method) use ($methodName): bool {
                    return !isset($method) || $method->name === $methodName;
                },
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
            static fn(ReflectionClass $c, ?ReflectionMethod $m = null): bool
                => $m === null || $m->name === $methodName,
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
            static fn(ReflectionClass $c, ?ReflectionMethod $m = null): bool
                => $m === null || $m->name === $methodName,
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
                function (ReflectionClass $class, ?ReflectionProperty $property) use ($propertyName): bool {
                    return !isset($property) || $property->name === $propertyName;
                },
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

    /**
     * Verifies that a final method inherited from a parent class cannot be woven and is skipped.
     */
    public function testParentFinalMethodIsNotMatched(): void
    {
        $child           = new class extends First {}; // publicFinalMethod is final in First
        $reflectionClass = new ReflectionClass($child);

        $pointcut = $this->createMock(Pointcut::class);
        $pointcut->method('getKind')->willReturn(Pointcut::KIND_METHOD);
        $pointcut->method('matches')->willReturn(true);

        $advice  = $this->createMock(Advice::class);
        $advisor = new GenericPointcutAdvisor($pointcut, $advice);

        $advices = $this->adviceMatcher->getAdvicesForClass($reflectionClass, ['advisor' => $advisor]);

        $methodAdvices = $advices[AspectContainer::METHOD_PREFIX] ?? [];
        $this->assertArrayNotHasKey('publicFinalMethod', $methodAdvices);
    }

    /**
     * Verifies that abstract methods cannot be woven and are skipped.
     */
    public function testAbstractMethodIsNotMatched(): void
    {
        $reflectionClass = new ReflectionClass(AdviceMatcherTestAbstractClass::class);

        $pointcut = $this->createMock(Pointcut::class);
        $pointcut->method('getKind')->willReturn(Pointcut::KIND_METHOD);
        $pointcut->method('matches')->willReturn(true);

        $advice  = $this->createMock(Advice::class);
        $advisor = new GenericPointcutAdvisor($pointcut, $advice);

        $advices = $this->adviceMatcher->getAdvicesForClass($reflectionClass, ['advisor' => $advisor]);

        $methodAdvices = $advices[AspectContainer::METHOD_PREFIX] ?? [];
        $this->assertArrayNotHasKey('abstractMethod', $methodAdvices);
        $this->assertArrayHasKey('concreteMethod', $methodAdvices);
    }

    /**
     * Verifies that when a class' parent is an AOP-proxied trait-holder (name contains the
     * Original suffix), advice matching resolves methods against that original parent class
     * instead of the (proxy) class passed in - private methods declared directly on the original
     * class must still be matched.
     */
    public function testResolvesOriginalClassWhenParentIsAopProxied(): void
    {
        $reflectionClass = new ReflectionClass(AdviceMatcherTestProxyChild::class);

        $pointcut = $this->createMock(Pointcut::class);
        $pointcut->method('getKind')->willReturn(Pointcut::KIND_METHOD);
        $pointcut->method('matches')->willReturn(true);

        $advice  = $this->createMock(Advice::class);
        $advisor = new GenericPointcutAdvisor($pointcut, $advice);

        $advices = $this->adviceMatcher->getAdvicesForClass($reflectionClass, ['advisor' => $advisor]);

        $methodAdvices = $advices[AspectContainer::METHOD_PREFIX] ?? [];
        // privateOriginal is declared directly on the Original parent, so it is matched
        // only if the original (parent) class was used for the declaring-class comparison.
        $this->assertArrayHasKey('privateOriginal', $methodAdvices);
    }

    /**
     * Verifies dynamic (KIND_INIT) class-level advice is collected.
     */
    public function testGetAdvicesForClassCollectsInitAdvice(): void
    {
        $pointcut = $this->createMock(Pointcut::class);
        $pointcut->method('getKind')->willReturn(Pointcut::KIND_CLASS | Pointcut::KIND_INIT);
        $pointcut->method('matches')->willReturn(true);

        $advice  = $this->createMock(Advice::class);
        $advisor = new GenericPointcutAdvisor($pointcut, $advice);

        $advices = $this->adviceMatcher->getAdvicesForClass($this->reflectionClass, ['advisor' => $advisor]);

        $this->assertSame($advice, $advices[AspectContainer::INIT_PREFIX]['root']['advisor'] ?? null);
    }

    /**
     * Verifies static (KIND_STATIC_INIT) class-level advice is collected.
     */
    public function testGetAdvicesForClassCollectsStaticInitAdvice(): void
    {
        $pointcut = $this->createMock(Pointcut::class);
        $pointcut->method('getKind')->willReturn(Pointcut::KIND_CLASS | Pointcut::KIND_STATIC_INIT);
        $pointcut->method('matches')->willReturn(true);

        $advice  = $this->createMock(Advice::class);
        $advisor = new GenericPointcutAdvisor($pointcut, $advice);

        $advices = $this->adviceMatcher->getAdvicesForClass($this->reflectionClass, ['advisor' => $advisor]);

        $this->assertSame($advice, $advices[AspectContainer::STATIC_INIT_PREFIX]['root']['advisor'] ?? null);
    }

    /**
     * Verifies introduction (KIND_INTRODUCTION) advice adds both trait and interface entries.
     */
    public function testGetAdvicesForClassCollectsIntroductionAdviceWithTraitAndInterface(): void
    {
        $pointcut = $this->createMock(Pointcut::class);
        $pointcut->method('getKind')->willReturn(Pointcut::KIND_CLASS | Pointcut::KIND_INTRODUCTION);
        $pointcut->method('matches')->willReturn(true);

        $advice  = new TraitIntroductionInfo(AdviceMatcherTestIntroducedTrait::class, AdviceMatcherTestIntroducedInterface::class);
        $advisor = new GenericPointcutAdvisor($pointcut, $advice);

        $advices = $this->adviceMatcher->getAdvicesForClass($this->reflectionClass, ['advisor' => $advisor]);

        $this->assertSame(
            $advice,
            $advices[AspectContainer::INTRODUCTION_TRAIT_PREFIX]['root']['\\' . AdviceMatcherTestIntroducedTrait::class] ?? null,
        );
        $this->assertSame(
            $advice,
            $advices[AspectContainer::INTRODUCTION_INTERFACE_PREFIX]['root']['\\' . AdviceMatcherTestIntroducedInterface::class] ?? null,
        );
    }

    /**
     * Verifies that an introduction advice with only an interface (no trait) yields only the
     * interface entry.
     */
    public function testGetAdvicesForClassCollectsIntroductionAdviceWithOnlyInterface(): void
    {
        $pointcut = $this->createMock(Pointcut::class);
        $pointcut->method('getKind')->willReturn(Pointcut::KIND_CLASS | Pointcut::KIND_INTRODUCTION);
        $pointcut->method('matches')->willReturn(true);

        $advice = $this->createMock(IntroductionInfo::class);
        $advice->method('getTrait')->willReturn('');
        $advice->method('getInterface')->willReturn(AdviceMatcherTestIntroducedInterface::class);

        $advisor = new GenericPointcutAdvisor($pointcut, $advice);

        $advices = $this->adviceMatcher->getAdvicesForClass($this->reflectionClass, ['advisor' => $advisor]);

        $this->assertArrayNotHasKey(AspectContainer::INTRODUCTION_TRAIT_PREFIX, $advices);
        $this->assertSame(
            $advice,
            $advices[AspectContainer::INTRODUCTION_INTERFACE_PREFIX]['root']['\\' . AdviceMatcherTestIntroducedInterface::class] ?? null,
        );
    }

    /**
     * Verifies introduction advice is skipped entirely when matched against a trait (traits
     * cannot receive introductions).
     */
    public function testIntroductionAdviceIsSkippedForTraitContext(): void
    {
        $reflectionClass = new ReflectionClass(AdviceMatcherTestIntroducedTrait::class);

        $pointcut = $this->createMock(Pointcut::class);
        $pointcut->method('getKind')->willReturn(Pointcut::KIND_CLASS | Pointcut::KIND_INTRODUCTION);
        $pointcut->method('matches')->willReturn(true);

        $advice  = new TraitIntroductionInfo(AdviceMatcherTestIntroducedTrait::class, AdviceMatcherTestIntroducedInterface::class);
        $advisor = new GenericPointcutAdvisor($pointcut, $advice);

        $advices = $this->adviceMatcher->getAdvicesForClass($reflectionClass, ['advisor' => $advisor]);

        $this->assertArrayNotHasKey(AspectContainer::INTRODUCTION_TRAIT_PREFIX, $advices);
        $this->assertArrayNotHasKey(AspectContainer::INTRODUCTION_INTERFACE_PREFIX, $advices);
    }

    /**
     * Verifies that getAdvicesForFunctions() short-circuits to an empty array when function
     * interception is not enabled.
     */
    public function testGetAdvicesForFunctionsReturnsEmptyArrayWhenFeatureDisabled(): void
    {
        $adviceMatcher = new AdviceMatcher(isInterceptFunctions: false);

        $reflectionFile = new ReflectionFile(__FILE__);
        $namespace      = $reflectionFile->getFileNamespace(__NAMESPACE__);

        $pointcut = $this->createMock(Pointcut::class);
        $pointcut->expects($this->never())->method('getKind');

        $advice  = $this->createMock(Advice::class);
        $advisor = new GenericPointcutAdvisor($pointcut, $advice);

        $advices = $adviceMatcher->getAdvicesForFunctions($namespace, ['advisor' => $advisor]);

        $this->assertSame([], $advices);
    }

    /**
     * Verifies that getAdvicesForFunctions() matches an internal PHP function by name when
     * function interception is enabled.
     */
    public function testGetAdvicesForFunctionsMatchesInternalFunction(): void
    {
        $adviceMatcher = new AdviceMatcher(isInterceptFunctions: true);

        $reflectionFile = new ReflectionFile(__FILE__);
        $namespace      = $reflectionFile->getFileNamespace(__NAMESPACE__);

        $pointcut = $this->createMock(Pointcut::class);
        $pointcut->method('getKind')->willReturn(Pointcut::KIND_FUNCTION);
        $pointcut->method('matches')->willReturnCallback(
            static fn(ReflectionClass|ReflectionFileNamespace $context, ?ReflectionFunction $reflector = null): bool
                => $reflector === null || $reflector->name === 'strlen',
        );

        $advice  = $this->createMock(Advice::class);
        $advisor = new GenericPointcutAdvisor($pointcut, $advice);

        $advices = $adviceMatcher->getAdvicesForFunctions($namespace, ['advisor' => $advisor]);

        $this->assertSame(
            $advice,
            $advices[AspectContainer::FUNCTION_PREFIX]['strlen']['advisor'] ?? null,
        );
    }

    /**
     * Verifies that getAdvicesForFunctions() skips non-function-kind advisors and advisors whose
     * namespace-level match fails, so no functions are collected.
     */
    public function testGetAdvicesForFunctionsSkipsNonMatchingNamespace(): void
    {
        $adviceMatcher = new AdviceMatcher(isInterceptFunctions: true);

        $reflectionFile = new ReflectionFile(__FILE__);
        $namespace      = $reflectionFile->getFileNamespace(__NAMESPACE__);

        $pointcut = $this->createMock(Pointcut::class);
        $pointcut->method('getKind')->willReturn(Pointcut::KIND_FUNCTION);
        $pointcut->method('matches')->willReturn(false);

        $advice  = $this->createMock(Advice::class);
        $advisor = new GenericPointcutAdvisor($pointcut, $advice);

        $advices = $adviceMatcher->getAdvicesForFunctions($namespace, ['advisor' => $advisor]);

        $this->assertSame([], $advices);
    }
}

abstract class AdviceMatcherTestAbstractClass
{
    abstract public function abstractMethod(): void;

    public function concreteMethod(): void {}
}

class AdviceMatcherTestFooOriginal
{
    // @phpstan-ignore method.unused (only ever reached via reflection in AdviceMatcher, never called directly)
    private function privateOriginal(): void {}
}

class AdviceMatcherTestProxyChild extends AdviceMatcherTestFooOriginal {}

trait AdviceMatcherTestIntroducedTrait {}

interface AdviceMatcherTestIntroducedInterface {}

final class AdviceMatcherTestIntroducedTraitConsumer
{
    use AdviceMatcherTestIntroducedTrait;
}
