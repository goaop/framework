<?php

declare(strict_types=1);

namespace Go\Proxy\Generator;

use Go\Aop\AspectException;
use Go\Aop\Framework\AfterInterceptor;
use Go\Aop\Framework\AfterThrowingInterceptor;
use Go\Aop\Framework\AroundInterceptor;
use Go\Aop\Framework\BeforeInterceptor;
use Go\Aop\Framework\GeneratedInterceptor;
use Go\Stubs\Collision\A\SameNameAspect as AspectA;
use Go\Stubs\Collision\B\SameNameAspect as AspectB;
use PHPUnit\Framework\TestCase;

final class InterceptorListGeneratorTest extends TestCase
{
    public function testGeneratesContainerAdviceForClosureBackedAdvice(): void
    {
        $descriptor = GeneratedInterceptor::fromAdvice(
            'manual.around',
            new AroundInterceptor(static fn(): mixed => null, 20),
        );

        $code = (new InterceptorListGenerator([$descriptor]))->generate();

        $this->assertSame(
            <<<'PHP'
[
                \Go\Aop\Framework\Interceptor::around(\Go\Aop\Framework\The::advice('manual.around'), order: 20),
            ]
PHP,
            $code,
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
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('manual.before')),
                \Go\Aop\Framework\Interceptor::after(\Go\Aop\Framework\The::advice('manual.after')),
                \Go\Aop\Framework\Interceptor::around(\Go\Aop\Framework\The::advice('manual.around')),
                \Go\Aop\Framework\Interceptor::afterThrowing(\Go\Aop\Framework\The::advice('manual.afterThrowing')),
            ]
PHP,
            $code,
        );
    }

    public function testReferencesAspectsWithEqualShortNamesByFullyQualifiedName(): void
    {
        $descriptors = [
            GeneratedInterceptor::fromAdvice('a', new BeforeInterceptor(new AspectA()->beforeMethod(...))),
            GeneratedInterceptor::fromAdvice('b', new BeforeInterceptor(new AspectB()->beforeMethod(...))),
        ];

        $code = (new InterceptorListGenerator($descriptors))->generate();

        // Short names would collide (and resolve against the imports of the woven file), so every
        // class reference must be fully qualified and independent of the surrounding namespace
        $this->assertSame(
            <<<'PHP'
[
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::aspect(\Go\Stubs\Collision\A\SameNameAspect::class)->beforeMethod(...)),
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::aspect(\Go\Stubs\Collision\B\SameNameAspect::class)->beforeMethod(...)),
            ]
PHP,
            $code,
        );
    }

    public function testRejectsPlainStringAdvisorIds(): void
    {
        $this->expectException(AspectException::class);
        $this->expectExceptionMessage('expects generated interceptor descriptors');

        new InterceptorListGenerator(['advisor.Some\Aspect->advice']);
    }
}
