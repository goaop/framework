<?php

declare(strict_types=1);

namespace Go\Aop\Framework;

use Go\Aop\Aspect;
use Go\Aop\Intercept\Joinpoint;
use Go\Aop\Pointcut;
use Go\Aop\Pointcut\NamePointcut;
use Go\Aop\Support\GenericPointcutAdvisor;
use Go\Core\AdviceMatcher;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\Container;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class InterceptorTest extends TestCase
{
    protected function tearDown(): void
    {
        $instance = new ReflectionProperty(AspectKernel::class, 'instance');
        $instance->setValue(null, null);
        InterceptorTestAspect::$invocations = [];
    }

    private function initKernelWithTestAspect(): void
    {
        $kernel    = InterceptorTestAspectKernel::getInstance();
        $container = new Container();
        $container->registerAspect(new InterceptorTestAspect());

        $containerProperty = new ReflectionProperty(AspectKernel::class, 'container');
        $containerProperty->setValue($kernel, $container);
    }
    public function testCreatesBeforeInterceptor(): void
    {
        $interceptor = Interceptor::before(static function (Joinpoint $joinpoint): void {}, order: 10);

        $this->assertSame(10, $interceptor->getAdviceOrder());
    }

    public function testCreatesAfterInterceptor(): void
    {
        $interceptor = Interceptor::after(static function (Joinpoint $joinpoint): void {});

        $this->assertSame(0, $interceptor->getAdviceOrder());
    }

    public function testCreatesAroundInterceptor(): void
    {
        $advice      = static fn(Joinpoint $joinpoint): mixed => $joinpoint->proceed();
        $interceptor = Interceptor::around($advice);

        $this->assertSame($advice, $interceptor->getRawAdvice());
    }

    public function testCreatesAfterThrowingInterceptor(): void
    {
        $advice      = static function (Joinpoint $joinpoint, \Throwable $throwable): void {};
        $interceptor = Interceptor::afterThrowing($advice);

        $this->assertSame($advice, $interceptor->getRawAdvice());
    }

    public function testAspectMethodInterceptorIsUninitializedAfterConstruction(): void
    {
        // No kernel or aspect is required to CREATE the interceptor - everything is deferred
        $interceptor = Interceptor::before(InterceptorTestAspect::class, 'recordInvocation', order: 42);

        $this->assertTrue(new ReflectionClass(BeforeInterceptor::class)->isUninitializedLazyObject($interceptor));
    }

    public function testInterceptorStaysUninitializedWhenPointcutDoesNotMatch(): void
    {
        $interceptor = Interceptor::before(InterceptorTestAspect::class, 'recordInvocation');
        $advisor     = new GenericPointcutAdvisor(
            new NamePointcut(Pointcut::KIND_ALL, 'Never\Matching\ClassName', true),
            $interceptor,
        );

        $matchedAdvices = new AdviceMatcher()->getAdvicesForClass(
            new ReflectionClass(\stdClass::class),
            ['advisor.never-matching' => $advisor],
        );

        // Advisor matching (and the sorting applied to matched advices afterwards) never
        // touches the advice of a non-matching advisor, so the interceptor stays a ghost
        $this->assertSame([], $matchedAdvices);
        $this->assertTrue(new ReflectionClass(BeforeInterceptor::class)->isUninitializedLazyObject($interceptor));
    }

    public function testInterceptorInitializesOnInvocationWithBehaviorIntact(): void
    {
        $this->initKernelWithTestAspect();
        $interceptor = Interceptor::before(InterceptorTestAspect::class, 'recordInvocation', order: 7, expression: 'some(expression)');
        $joinpoint   = $this->createStub(Joinpoint::class);
        $joinpoint->method('proceed')->willReturn('proceeded');

        $reflection = new ReflectionClass(BeforeInterceptor::class);
        $this->assertTrue($reflection->isUninitializedLazyObject($interceptor));

        $this->assertSame('proceeded', $interceptor->invoke($joinpoint));

        $this->assertFalse($reflection->isUninitializedLazyObject($interceptor));
        $this->assertSame([$joinpoint], InterceptorTestAspect::$invocations);
        $this->assertSame(7, $interceptor->getAdviceOrder());
        $this->assertSame('some(expression)', $interceptor->pointcutExpression);
    }
}

final class InterceptorTestAspectKernel extends AspectKernel
{
    protected function configureAop(AspectContainer $container): void
    {
        $container->registerAspect(new InterceptorTestAspect());
    }
}

final class InterceptorTestAspect implements Aspect
{
    /** @var list<Joinpoint> */
    public static array $invocations = [];

    public function recordInvocation(Joinpoint $joinpoint): void
    {
        self::$invocations[] = $joinpoint;
    }
}
