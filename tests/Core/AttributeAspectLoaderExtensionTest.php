<?php

declare(strict_types=1);

namespace Go\Core;

use Go\Aop\AspectException;
use Go\Aop\Pointcut\PointcutGrammar;
use Go\Aop\Pointcut\PointcutLexer;
use Go\Aop\Pointcut\PointcutParser;
use Go\Stubs\AttributeAspectLoaderExtensionTestPrivateAspect;
use Go\Stubs\AttributeAspectLoaderExtensionTestPublicAspect;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AttributeAspectLoaderExtensionTest extends TestCase
{
    private AttributeAspectLoaderExtension $extension;

    protected function setUp(): void
    {
        $container       = $this->createStub(AspectContainer::class);
        $this->extension = new AttributeAspectLoaderExtension(new PointcutLexer(), new PointcutParser(new PointcutGrammar($container)));
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
}
