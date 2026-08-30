<?php

declare(strict_types=1);

namespace Go\Proxy\Generator;

use Go\Aop\AspectException;
use Go\Aop\Framework\AfterInterceptor;
use Go\Aop\Framework\AfterThrowingInterceptor;
use Go\Aop\Framework\AroundInterceptor;
use Go\Aop\Framework\BeforeInterceptor;
use Go\Aop\Framework\GeneratedInterceptor;
use PHPUnit\Framework\TestCase;

final class InterceptorListGeneratorTest extends TestCase
{
    public function testGeneratesContainerAdviceForClosureBackedAdvice(): void
    {
        $descriptor = GeneratedInterceptor::fromAdvice(
            'manual.around',
            new AroundInterceptor(static fn(): mixed => null, 20)
        );

        $code = (new InterceptorListGenerator([$descriptor]))->generate();

        $this->assertSame([], InterceptorListGenerator::aspectClasses([$descriptor]));
        $this->assertSame(
            <<<'PHP'
[
                Interceptor::around(The::advice('manual.around'), order: 20),
            ]
PHP,
            $code
        );
    }

    public function testGeneratesMatchingFactoryCallForEveryAdviceType(): void
    {
        $noop        = static fn(): mixed => null;
        $descriptors = [
            GeneratedInterceptor::fromAdvice('manual.before', new BeforeInterceptor($noop)),
            GeneratedInterceptor::fromAdvice('manual.after', new AfterInterceptor($noop)),
            GeneratedInterceptor::fromAdvice('manual.around', new AroundInterceptor($noop)),
            GeneratedInterceptor::fromAdvice('manual.afterThrowing', new AfterThrowingInterceptor($noop)),
        ];

        $code = (new InterceptorListGenerator($descriptors))->generate();

        $this->assertSame(
            <<<'PHP'
[
                Interceptor::before(The::advice('manual.before')),
                Interceptor::after(The::advice('manual.after')),
                Interceptor::around(The::advice('manual.around')),
                Interceptor::afterThrowing(The::advice('manual.afterThrowing')),
            ]
PHP,
            $code
        );
    }

    public function testRejectsPlainStringAdvisorIds(): void
    {
        $this->expectException(AspectException::class);
        $this->expectExceptionMessage('expects generated interceptor descriptors');

        new InterceptorListGenerator(['advisor.Some\Aspect->advice']);
    }
}
