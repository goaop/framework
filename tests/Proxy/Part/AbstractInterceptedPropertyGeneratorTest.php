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

namespace Go\Proxy\Part;

use Go\Aop\Framework\BeforeInterceptor;
use Go\Aop\Framework\GeneratedInterceptor;
use Go\Proxy\Generator\GeneratedCodePrinter;
use Go\Proxy\Generator\ProxyImports;
use Go\Stubs\Generator\PropertyTypeStubs;
use PHPUnit\Framework\TestCase;
use PhpParser\Node\Stmt\Property as PropertyNode;
use ReflectionProperty;

/**
 * Minimal concrete subclass exposing the protected/private branches of
 * AbstractInterceptedPropertyGenerator that InterceptedPropertyGenerator and
 * TraitInterceptedPropertyGenerator do not exercise on their own (union/intersection/
 * untyped property phpDoc rendering, array-typed union detection).
 */
final class AbstractInterceptedPropertyGeneratorTestSubject extends AbstractInterceptedPropertyGenerator
{
    public function getNode(): PropertyNode
    {
        return $this->createBasePropertyGenerator()->getNode();
    }

    public function exposeIsArrayTypedProperty(): bool
    {
        return $this->isArrayTypedProperty();
    }

    public function exposeFieldAccessDocComment(): string
    {
        return $this->createFieldAccessDocComment()->getText();
    }
}

/**
 * Test case for the shared property-hook generation logic in the abstract base class.
 */
class AbstractInterceptedPropertyGeneratorTest extends TestCase
{
    public function testIsArrayTypedPropertyDetectsArrayWithinUnionType(): void
    {
        $generator = new AbstractInterceptedPropertyGeneratorTestSubject(
            new ReflectionProperty(PropertyTypeStubs::class, 'unionArrayProp'),
        );

        $this->assertTrue($generator->exposeIsArrayTypedProperty());
    }

    /**
     * The get hook of a nullable property without default catches Error: the import is registered on construction,
     * the class proxy generator collects the file imports before it builds the property nodes (#819)
     */
    public function testNullablePropertyWithoutDefaultImportsErrorUpFront(): void
    {
        $advice   = GeneratedInterceptor::fromAdvice('test', new BeforeInterceptor(static function (): void {}));
        $imports  = new ProxyImports('App');
        $nullable = new InterceptedPropertyGenerator(
            new ReflectionProperty(PropertyTypeStubs::class, 'uninitializedNullableProp'),
            [$advice],
            $imports,
        );
        $this->assertSame(['Error' => null], $imports->getUses());
        $this->assertStringContainsString('} catch (Error) {', new GeneratedCodePrinter()->prettyPrint([$nullable->getNode()]));

        // A property that can not hold null needs no try block
        $otherImports = new ProxyImports('App');
        new InterceptedPropertyGenerator(new ReflectionProperty(PropertyTypeStubs::class, 'intersectionProp'), [$advice], $otherImports);
        $this->assertArrayNotHasKey('Error', $otherImports->getUses());
    }

    public function testFieldAccessDocCommentRendersMixedForUntypedProperty(): void
    {
        $generator = new AbstractInterceptedPropertyGeneratorTestSubject(
            new ReflectionProperty(PropertyTypeStubs::class, 'untypedProp'),
        );

        $this->assertStringContainsString('FieldAccess<self, mixed>', $generator->exposeFieldAccessDocComment());
    }

    public function testFieldAccessDocCommentRendersNullableNamedType(): void
    {
        $generator = new AbstractInterceptedPropertyGeneratorTestSubject(
            new ReflectionProperty(PropertyTypeStubs::class, 'nullableProp'),
        );

        $this->assertStringContainsString('FieldAccess<self, ?string>', $generator->exposeFieldAccessDocComment());
    }

    public function testFieldAccessDocCommentRendersUnionType(): void
    {
        $generator = new AbstractInterceptedPropertyGeneratorTestSubject(
            new ReflectionProperty(PropertyTypeStubs::class, 'unionArrayProp'),
        );

        $this->assertStringContainsString('FieldAccess<self, array|int>', $generator->exposeFieldAccessDocComment());
    }

    public function testFieldAccessDocCommentRendersIntersectionType(): void
    {
        $generator = new AbstractInterceptedPropertyGeneratorTestSubject(
            new ReflectionProperty(PropertyTypeStubs::class, 'intersectionProp'),
        );

        $this->assertStringContainsString(
            'FieldAccess<self, \Countable&\Iterator>',
            $generator->exposeFieldAccessDocComment(),
        );
    }
}
