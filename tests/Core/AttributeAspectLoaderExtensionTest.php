<?php

declare(strict_types=1);

namespace Go\Core;

use Attribute;
use Go\Aop\Aspect;
use Go\Aop\Exception\AspectException;
use Go\Aop\Exception\PointcutSyntaxException;
use Go\Aop\Framework\AbstractInterceptor as AbstractInterceptorClass;
use Go\Aop\Pointcut;
use Go\Aop\Pointcut\PointcutGrammar;
use Go\Aop\Pointcut\PointcutLexer;
use Go\Aop\Pointcut\PointcutParser;
use Go\Aop\Support\GenericPointcutAdvisor;
use Go\Lang\Attribute\After;
use Go\Lang\Attribute\Before;
use Go\Lang\Attribute\Pointcut as PointcutAttribute;
use Go\Stubs\AttributeAspectLoaderExtensionTestPrivateAspect;
use Go\Stubs\AttributeAspectLoaderExtensionTestPublicAspect;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AttributeAspectLoaderExtensionTest extends TestCase
{
    private AttributeAspectLoaderExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new AttributeAspectLoaderExtension(new PointcutLexer(), new PointcutParser(new PointcutGrammar()));
    }

    public function testLoadsAdvisorForPublicAdviceMethod(): void
    {
        $aspect      = new AttributeAspectLoaderExtensionTestPublicAspect();
        $loadedItems = $this->extension->load(new ReflectionClass($aspect));

        $this->assertArrayHasKey($aspect::class . '->publicAdvice', $loadedItems);
    }

    public function testLoadsAdvicesWithoutCreatingTheAspect(): void
    {
        $loadedItems = $this->extension->load(new ReflectionClass(AttributeAspectLoaderExtensionTestUncreatableAspect::class));

        $advisor = $loadedItems[AttributeAspectLoaderExtensionTestUncreatableAspect::class . '->advice'] ?? null;
        $this->assertInstanceOf(GenericPointcutAdvisor::class, $advisor);
        $interceptor = $advisor->getAdvice();
        $this->assertInstanceOf(AbstractInterceptorClass::class, $interceptor);
        $this->assertSame(AttributeAspectLoaderExtensionTestUncreatableAspect::class, $interceptor->aspectClass);
        $this->assertSame('advice', $interceptor->adviceMethodName);
        $this->assertSame(3, $interceptor->adviceOrder);
        $this->assertTrue(new ReflectionClass($interceptor::class)->isUninitializedLazyObject($interceptor));
    }

    public function testRejectsNonPublicAdviceMethod(): void
    {
        $aspect = new AttributeAspectLoaderExtensionTestPrivateAspect();

        $this->expectException(AspectException::class);
        $this->expectExceptionMessage('first-class advice callables require all advice methods to be public');

        $this->extension->load(new ReflectionClass($aspect));
    }

    public function testForeignAttributeOnAdviceMethodIsIgnored(): void
    {
        $aspect      = new AttributeAspectLoaderExtensionTestForeignAttributeAspect();
        $loadedItems = $this->extension->load(new ReflectionClass($aspect));

        $this->assertSame([$aspect::class . '->advice'], array_keys($loadedItems));
    }

    public function testPointcutAndAdviceOnOneMethodAreBothLoaded(): void
    {
        $aspect      = new AttributeAspectLoaderExtensionTestPointcutAndAdviceAspect();
        $loadedItems = $this->extension->load(new ReflectionClass($aspect));

        $methodId = $aspect::class . '->pointcutAndAdvice';
        $this->assertInstanceOf(Pointcut::class, $loadedItems[$methodId] ?? null);
        $this->assertInstanceOf(
            GenericPointcutAdvisor::class,
            $loadedItems[$methodId . AttributeAspectLoaderExtension::ADVISOR_ID_SUFFIX] ?? null,
        );
    }

    public function testSecondAdviceAttributeOnOneMethodFailsLoudly(): void
    {
        $aspect = new AttributeAspectLoaderExtensionTestTwoAdvicesAspect();

        $this->expectException(AspectException::class);
        $this->expectExceptionMessage(
            'Advice method ' . AttributeAspectLoaderExtensionTestTwoAdvicesAspect::class . '::twoAdvices() declares 2 advice attributes',
        );

        $this->extension->load(new ReflectionClass($aspect));
    }

    public function testPointcutSyntaxErrorCitesTheMethod(): void
    {
        $aspect = new AttributeAspectLoaderExtensionTestBrokenPointcutAspect();

        $this->expectException(PointcutSyntaxException::class);
        $this->expectExceptionMessage(AttributeAspectLoaderExtensionTestBrokenPointcutAspect::class . '->brokenAdvice');

        $this->extension->load(new ReflectionClass($aspect));
    }
}

#[Attribute(Attribute::TARGET_METHOD)]
final class AttributeAspectLoaderExtensionTestForeignAttribute {}

/**
 * Aspect without instance properties (PHP can not make it lazy) that must never be created by loading it
 */
final class AttributeAspectLoaderExtensionTestUncreatableAspect implements Aspect
{
    public function __construct()
    {
        throw new LogicException('Loading an aspect must not create it');
    }

    #[Before('execution(public NonExistent\**->*(*))', order: 3)]
    public function advice(): void {}
}

final class AttributeAspectLoaderExtensionTestForeignAttributeAspect implements Aspect
{
    #[AttributeAspectLoaderExtensionTestForeignAttribute]
    #[Before('execution(public NonExistent\**->*(*))')]
    public function advice(): void {}
}

final class AttributeAspectLoaderExtensionTestPointcutAndAdviceAspect implements Aspect
{
    #[PointcutAttribute('execution(public NonExistent\**->*(*))')]
    #[Before('execution(public NonExistent\**->*(*))')]
    public function pointcutAndAdvice(): void {}
}

final class AttributeAspectLoaderExtensionTestTwoAdvicesAspect implements Aspect
{
    #[Before('execution(public NonExistent\**->*(*))')]
    #[After('execution(public NonExistent\**->*(*))')]
    public function twoAdvices(): void {}
}

final class AttributeAspectLoaderExtensionTestBrokenPointcutAspect implements Aspect
{
    #[Before('execution(public NonExistent->)')]
    public function brokenAdvice(): void {}
}
