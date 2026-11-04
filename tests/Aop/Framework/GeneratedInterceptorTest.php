<?php

declare(strict_types=1);

namespace Go\Aop\Framework;

use Go\Aop\Advice;
use Go\Aop\AdviceTypeEnum;
use Go\Aop\Exception\AspectException;
use Go\Aop\Intercept\Joinpoint;
use Go\Stubs\AnonymousAdviceAspect;
use Go\Stubs\AttributeAspectLoaderExtensionTestPublicAspect;
use Go\Stubs\Php86PartialAdviceAspect;
use LogicException;
use PhpParser\Node\Expr;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class GeneratedInterceptorTest extends TestCase
{
    public function testCreatesContainerAdviceDescriptorForNonAspectCallableAdvice(): void
    {
        $interceptor = GeneratedInterceptor::fromAdvice(
            'manual-advisor',
            new BeforeInterceptor(static function (Joinpoint $joinpoint): void {}, 10),
        );

        $this->assertSame('before', $interceptor->factoryMethod);
        $this->assertNull($interceptor->aspectClass);
        $this->assertNull($interceptor->adviceMethod);
        $this->assertSame(10, $interceptor->order);
        $this->assertSame('manual-advisor', $interceptor->advisorId);
        $this->assertTrue($interceptor->usesContainerAdvice);
    }

    /**
     * @param class-string<AbstractInterceptor> $interceptorClass
     */
    #[DataProvider('adviceTypeSource')]
    public function testResolvesFactoryMethodFromAdviceType(string $interceptorClass, string $expectedFactoryMethod): void
    {
        $interceptor = GeneratedInterceptor::fromAdvice(
            'manual-advisor',
            new $interceptorClass(static fn(): mixed => null),
        );

        $this->assertSame($expectedFactoryMethod, $interceptor->factoryMethod);
        $this->assertTrue($interceptor->usesContainerAdvice);
        $this->assertSame('manual-advisor', $interceptor->advisorId);
    }

    /**
     * @return array<string, array{class-string<AbstractInterceptor>, string}>
     */
    public static function adviceTypeSource(): array
    {
        return [
            'before'        => [BeforeInterceptor::class, AdviceTypeEnum::Before->value],
            'after'         => [AfterInterceptor::class, AdviceTypeEnum::After->value],
            'around'        => [AroundInterceptor::class, AdviceTypeEnum::Around->value],
            'afterThrowing' => [AfterThrowingInterceptor::class, AdviceTypeEnum::AfterThrowing->value],
        ];
    }

    public function testCreatesAspectBackedDescriptorForAspectScopedAdvice(): void
    {
        $aspect = new AttributeAspectLoaderExtensionTestPublicAspect();
        $advice = new ReflectionMethod($aspect, 'publicAdvice')->getClosure($aspect);

        $interceptor = GeneratedInterceptor::fromAdvice(
            'advisor.' . $aspect::class . '->publicAdvice',
            new BeforeInterceptor($advice),
        );

        $this->assertSame('before', $interceptor->factoryMethod);
        $this->assertSame($aspect::class, $interceptor->aspectClass);
        $this->assertSame('publicAdvice', $interceptor->adviceMethod);
        $this->assertFalse($interceptor->usesContainerAdvice);
    }

    public function testCreatesAspectBackedDescriptorForFirstClassCallableOfAspectMethod(): void
    {
        $aspect = new AnonymousAdviceAspect();

        $interceptor = GeneratedInterceptor::fromAdvice('advisor.fcc', new AroundInterceptor($aspect->advice(...)));

        $this->assertSame(AnonymousAdviceAspect::class, $interceptor->aspectClass);
        $this->assertSame('advice', $interceptor->adviceMethod);
        $this->assertFalse($interceptor->usesContainerAdvice);
    }

    public function testCreatesContainerAdviceDescriptorForClosureCreatedInsideAspect(): void
    {
        // The closure is scoped to the aspect, but it is not an aspect method (issue #790)
        $interceptor = GeneratedInterceptor::fromAdvice(
            'advisor.closure',
            new AroundInterceptor(new AnonymousAdviceAspect()->closureAdvice()),
        );

        $this->assertNull($interceptor->aspectClass);
        $this->assertNull($interceptor->adviceMethod);
        $this->assertTrue($interceptor->usesContainerAdvice);
    }

    #[RequiresPhp('>= 8.6.0-dev')]
    public function testCreatesContainerAdviceDescriptorForPartialApplicationOfAspectMethod(): void
    {
        // A partial application of an aspect method is scoped to the aspect wherever it is created (issue #790)
        $interceptor = GeneratedInterceptor::fromAdvice(
            'advisor.partial',
            new AroundInterceptor(new Php86PartialAdviceAspect()->partialAdvice()),
        );

        $this->assertNull($interceptor->aspectClass);
        $this->assertNull($interceptor->adviceMethod);
        $this->assertTrue($interceptor->usesContainerAdvice);
    }

    public function testRejectsNonInterceptorAdvice(): void
    {
        $advice = new class implements Advice {
            public function getType(): AdviceTypeEnum
            {
                return AdviceTypeEnum::Before;
            }

            public function compileToPhp(): Expr
            {
                throw new LogicException('Not expected to be called');
            }
        };

        $this->expectException(AspectException::class);
        $this->expectExceptionMessage('unsupported advice');

        GeneratedInterceptor::fromAdvice('manual-advisor', $advice);
    }
}
