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

namespace Go\Core;

use Attribute;
use Go\Aop\Advice;
use Go\Aop\Aspect;
use Go\Aop\Framework\TraitIntroductionInfo;
use Go\Aop\Pointcut;
use Go\Aop\Pointcut\PointcutGrammar;
use Go\Aop\Pointcut\PointcutLexer;
use Go\Aop\Pointcut\PointcutParser;
use Go\Aop\Support\GenericPointcutAdvisor;
use Go\Lang\Attribute\AbstractAttribute;
use Go\Lang\Attribute\DeclareParents;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use UnexpectedValueException;

class IntroductionAspectExtensionTest extends TestCase
{
    private IntroductionAspectExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new IntroductionAspectExtension(
            new PointcutLexer(),
            new PointcutParser(new PointcutGrammar()),
        );
    }

    public function testLoadsIntroductionAdvisorForDeclareParentsProperty(): void
    {
        $aspect      = new IntroductionAspectExtensionTestAspect();
        $loadedItems = $this->extension->load($aspect, new ReflectionClass($aspect));

        $propertyId = IntroductionAspectExtensionTestAspect::class . '->introduction';
        $this->assertArrayHasKey($propertyId, $loadedItems);

        $advisor = $loadedItems[$propertyId];
        $this->assertInstanceOf(GenericPointcutAdvisor::class, $advisor);

        $pointcut = $advisor->getPointcut();
        $this->assertSame(
            Pointcut::KIND_INTRODUCTION | Pointcut::KIND_CLASS,
            $pointcut->getKind() & (Pointcut::KIND_INTRODUCTION | Pointcut::KIND_CLASS),
        );

        $advice = $advisor->getAdvice();
        $this->assertInstanceOf(TraitIntroductionInfo::class, $advice);
        $this->assertSame(IntroductionAspectExtensionTestTrait::class, $advice->getTrait());
        $this->assertSame(IntroductionAspectExtensionTestInterface::class, $advice->getInterface());
    }

    public function testThrowsForUnsupportedAttributeOnProperty(): void
    {
        $aspect = new IntroductionAspectExtensionTestInvalidAspect();

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Unsupported attribute class: ' . IntroductionAspectExtensionTestUnsupportedAttribute::class);

        $this->extension->load($aspect, new ReflectionClass($aspect));
    }

    public function testReturnsEmptyArrayForAspectWithoutAnnotatedProperties(): void
    {
        $aspect      = new IntroductionAspectExtensionTestEmptyAspect();
        $loadedItems = $this->extension->load($aspect, new ReflectionClass($aspect));

        $this->assertSame([], $loadedItems);
    }

    public function testGetAdviceThrowsForUnsupportedAttribute(): void
    {
        $extension = new class (new PointcutLexer(), new PointcutParser(new PointcutGrammar())) extends IntroductionAspectExtension {
            public function doGetAdvice(
                AbstractAttribute $interceptorAttribute,
                Aspect $aspect,
                ReflectionProperty $aspectProperty,
            ): Advice {
                return $this->getAdvice($interceptorAttribute, $aspect, $aspectProperty);
            }
        };

        $aspect     = new IntroductionAspectExtensionTestAspect();
        $reflection = new ReflectionProperty($aspect, 'introduction');

        $unsupportedAttribute = new class extends AbstractAttribute {
        };

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Unsupported attribute class: ' . $unsupportedAttribute::class);

        $extension->doGetAdvice($unsupportedAttribute, $aspect, $reflection);
    }
}

trait IntroductionAspectExtensionTestTrait
{
}

interface IntroductionAspectExtensionTestInterface
{
}

final class IntroductionAspectExtensionTestTraitConsumer
{
    use IntroductionAspectExtensionTestTrait;
}

final class IntroductionAspectExtensionTestAspect implements Aspect
{
    #[DeclareParents(
        'within(Go\Core\IntroductionAspectExtensionTestAspect)',
        IntroductionAspectExtensionTestInterface::class,
        IntroductionAspectExtensionTestTrait::class,
    )]
    public mixed $introduction = null;
}

final class IntroductionAspectExtensionTestEmptyAspect implements Aspect
{
    public mixed $plainProperty = null;
}

#[Attribute(Attribute::TARGET_PROPERTY)]
final class IntroductionAspectExtensionTestUnsupportedAttribute
{
}

final class IntroductionAspectExtensionTestInvalidAspect implements Aspect
{
    #[IntroductionAspectExtensionTestUnsupportedAttribute]
    public mixed $invalidIntroduction = null;
}
