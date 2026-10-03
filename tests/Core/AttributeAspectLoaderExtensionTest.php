<?php

declare(strict_types=1);

namespace Go\Core;

use Attribute;
use Go\Aop\Aspect;
use Go\Aop\AspectException;
use Go\Aop\Exception\PointcutSyntaxException;
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
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AttributeAspectLoaderExtensionTest extends TestCase
{
    private AttributeAspectLoaderExtension $extension;

    #[\Override]
    protected function setUp(): void
    {
        $this->extension = new AttributeAspectLoaderExtension(new PointcutLexer(), new PointcutParser(new PointcutGrammar()));
    }

    public function testLoadsAdvisorForPublicAdviceMethod(): void
    {
        $aspect      = new AttributeAspectLoaderExtensionTestPublicAspect();
        $loadedItems = $this->extension->load($aspect, new ReflectionClass($aspect));

        $this->assertArrayHasKey($aspect::class . '->publicAdvice', $loadedItems);
    }

    public function testRejectsNonPublicAdviceMethod(): void
    {
        $aspect = new AttributeAspectLoaderExtensionTestPrivateAspect();

        $this->expectException(AspectException::class);
        $this->expectExceptionMessage('first-class advice callables require all advice methods to be public');

        $this->extension->load($aspect, new ReflectionClass($aspect));
    }

    public function testForeignAttributeOnAdviceMethodIsIgnored(): void
    {
        $aspect      = new AttributeAspectLoaderExtensionTestForeignAttributeAspect();
        $loadedItems = $this->extension->load($aspect, new ReflectionClass($aspect));

        $this->assertSame([$aspect::class . '->advice'], array_keys($loadedItems));
    }

    public function testPointcutAndAdviceOnOneMethodAreBothLoaded(): void
    {
        $aspect      = new AttributeAspectLoaderExtensionTestPointcutAndAdviceAspect();
        $loadedItems = $this->extension->load($aspect, new ReflectionClass($aspect));

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

        $this->extension->load($aspect, new ReflectionClass($aspect));
    }

    public function testPointcutSyntaxErrorCitesTheMethod(): void
    {
        $aspect = new AttributeAspectLoaderExtensionTestBrokenPointcutAspect();

        $this->expectException(PointcutSyntaxException::class);
        $this->expectExceptionMessage(AttributeAspectLoaderExtensionTestBrokenPointcutAspect::class . '->brokenAdvice');

        $this->extension->load($aspect, new ReflectionClass($aspect));
    }
}

#[Attribute(Attribute::TARGET_METHOD)]
final class AttributeAspectLoaderExtensionTestForeignAttribute {}

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
