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

use Closure;
use Go\Aop\Exception\AspectCircularReferenceException;
use Go\Aop\Framework\AbstractInterceptor;
use Go\Aop\Framework\AfterInterceptor;
use Go\Aop\Framework\AfterThrowingInterceptor;
use Go\Aop\Framework\AroundInterceptor;
use Go\Aop\Framework\BeforeInterceptor;
use Go\Aop\Intercept\Joinpoint;
use Go\Stubs\CircularReference\Ledger;
use Go\Stubs\CircularReference\LedgerAspect;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Advices of an aspect that run while the container creates the aspect
 */
final class LazyServiceCreationTest extends TestCase
{
    private Container $container;

    /**
     * Interceptor run by the constructor of the dependency of the aspect, set once the aspect is registered
     */
    private ?AbstractInterceptor $interceptorOfDependency = null;

    /**
     * Failure of the joinpoint of the dependency, for after throwing advices
     */
    private ?LogicException $failureOfDependency = null;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->container->addLazyService(LedgerAspect::class, fn(): LedgerAspect => new LedgerAspect(
            new Ledger('main', function (): void {
                $this->interceptorOfDependency?->invoke(self::joinpoint('execution(Ledger->open())', $this->failureOfDependency));
            }),
        ));
    }

    protected function tearDown(): void
    {
        $this->assertSame([], LazyServiceCreation::$inProgress, 'Nothing is marked once the creation is over');
    }

    /**
     * @return iterable<string, array{Closure(Closure): AbstractInterceptor}>
     */
    public static function interceptors(): iterable
    {
        yield 'before' => [static fn(Closure $advice): AbstractInterceptor => new BeforeInterceptor($advice)];
        yield 'after' => [static fn(Closure $advice): AbstractInterceptor => new AfterInterceptor($advice)];
        yield 'around' => [static fn(Closure $advice): AbstractInterceptor => new AroundInterceptor($advice)];
    }

    /**
     * @param Closure(Closure): AbstractInterceptor $interceptor
     */
    #[DataProvider('interceptors')]
    public function testAdviceRunningWhileItsAspectIsCreatedIsReported(Closure $interceptor): void
    {
        $aspect                        = $this->container->getService(LedgerAspect::class);
        $this->interceptorOfDependency = $interceptor($aspect->beforeRecord(...));

        try {
            // The first advice reads a property of the lazy aspect, which creates the aspect
            $aspect->beforeRecord(self::joinpoint('execution(Report->render())'));
            $this->fail('Running an advice of the aspect while it is created must fail');
        } catch (AspectCircularReferenceException $exception) {
            $path = [Ledger::class . '->__construct()', Ledger::class . '->open()', LedgerAspect::class . '->beforeRecord()'];
            $this->assertSame(LedgerAspect::class, $exception->aspectClass);
            $this->assertSame($path, $exception->path);
            $this->assertStringContainsString(
                sprintf(
                    'Circular reference detected for the aspect "%s", path: "%s". ',
                    LedgerAspect::class,
                    implode(' -> ', [LedgerAspect::class, ...$path]),
                ),
                $exception->getMessage(),
            );
            $this->assertStringContainsString('ran its advice at execution(Ledger->open())', $exception->getMessage());
        }
    }

    public function testAdviceReadingNoPropertyOfTheAspectIsReportedToo(): void
    {
        $aspect                        = $this->container->getService(LedgerAspect::class);
        $this->interceptorOfDependency = new BeforeInterceptor($aspect->beforeOpen(...));

        // Without the check, the advice would run on the aspect being created without any error
        try {
            $aspect->beforeRecord(self::joinpoint('execution(Report->render())'));
            $this->fail('Running an advice of the aspect while it is created must fail');
        } catch (AspectCircularReferenceException $exception) {
            $this->assertSame(
                [Ledger::class . '->__construct()', Ledger::class . '->open()', LedgerAspect::class . '->beforeOpen()'],
                $exception->path,
            );
        }
    }

    public function testAfterThrowingAdviceIsReportedWhenItRuns(): void
    {
        $aspect                        = $this->container->getService(LedgerAspect::class);
        $this->interceptorOfDependency = new AfterThrowingInterceptor($aspect->beforeOpen(...));
        $this->failureOfDependency     = new LogicException('failed');

        $this->expectException(AspectCircularReferenceException::class);

        $aspect->beforeRecord(self::joinpoint('execution(Report->render())'));
    }

    public function testAspectIsCreatedAgainAfterTheFailure(): void
    {
        $aspect                        = $this->container->getService(LedgerAspect::class);
        $this->interceptorOfDependency = new BeforeInterceptor($aspect->beforeRecord(...));
        try {
            $aspect->beforeRecord(self::joinpoint('execution(Report->render())'));
        } catch (AspectCircularReferenceException) {
        }

        // The pointcut no longer advises the dependency: the creation succeeds
        $this->interceptorOfDependency = null;
        new BeforeInterceptor($aspect->beforeRecord(...))->invoke(self::joinpoint('execution(Report->render())'));

        $this->assertSame(['main:execution(Report->render())'], $aspect->calls);
    }

    public function testAdviceOfAnAspectThatIsAlreadyCreatedRunsWhileAnotherServiceIsCreated(): void
    {
        $aspect = $this->container->getService(LedgerAspect::class);
        $aspect->beforeRecord(self::joinpoint('execution(Report->render())'));

        $this->container->addLazyService(Ledger::class, static fn(): Ledger => new Ledger('second', static function () use ($aspect): void {
            new BeforeInterceptor($aspect->beforeRecord(...))->invoke(self::joinpoint('execution(Ledger->open())'));
        }));
        $this->assertSame('second', $this->container->getService(Ledger::class)->name);

        $this->assertSame(['main:execution(Report->render())', 'main:execution(Ledger->open())'], $aspect->calls);
    }

    public function testCallPathIsEmptyForAnObjectThatIsNotBeingCreated(): void
    {
        $this->assertSame([], LazyServiceCreation::callsSinceCreationOf(new Ledger('free', static function (): void {})));
    }

    private static function joinpoint(string $description, ?LogicException $failure = null): Joinpoint
    {
        $joinpoint = self::createStub(Joinpoint::class);
        $joinpoint->method('__toString')->willReturn($description);
        if ($failure !== null) {
            $joinpoint->method('proceed')->willThrowException($failure);
        }

        return $joinpoint;
    }
}
