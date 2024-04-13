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
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

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
            ->expects($this->any())
            ->method('matches')
            ->willReturnCallback(
                function (ReflectionClass $class, ReflectionMethod|null $method) use ($methodName): bool {
                    return !isset($method) || $method->name === $methodName;
                }
            )
        ;
        $pointcut
            ->expects($this->any())
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
     * Check that list of advices for fields works correctly
     */
    public function testGetSinglePropertyAdviceForClassFromAdvisor(): void
    {
        $propertyName = 'adviceMatcher'; // $this->adviceMatcher;

        $pointcut = $this->createMock(Pointcut::class);
        $pointcut
            ->expects($this->any())
            ->method('matches')
            ->willReturnCallback(
                function (ReflectionClass $class, ReflectionProperty|null $property) use ($propertyName): bool {
                    return !isset($property) || $property->name === $propertyName;
                }
            )
        ;
        $pointcut
            ->expects($this->any())
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
}
