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

use Go\Aop\Advice;
use Go\Aop\Aspect;
use Go\Aop\Pointcut;
use Go\Aop\Pointcut\PointcutGrammar;
use Go\Aop\Pointcut\PointcutLexer;
use Go\Aop\Pointcut\PointcutParser;
use Go\Aop\Pointcut\TruePointcut;
use Go\Aop\Support\GenericPointcutAdvisor;
use Go\Lang\Attribute\Before;
use LogicException;
use PHPUnit\Framework\TestCase;

final class AdvisorIndexTest extends TestCase
{
    public function testReportsNoPointcutKindWithoutAdvisors(): void
    {
        $index = new AdvisorIndex(new Container(), $this->createLoader());

        $this->assertSame([], $index->getAdvisors());
        $this->assertFalse($index->hasPointcutKind(Pointcut::KIND_INIT));
        $this->assertFalse($index->hasPointcutKind(Pointcut::KIND_FUNCTION));
    }

    public function testReportsPointcutKindsOfRegisteredAdvisors(): void
    {
        $container = new Container();
        $container->add('advisor.init', $this->createAdvisor(Pointcut::KIND_INIT | Pointcut::KIND_CLASS));
        $index = new AdvisorIndex($container, $this->createLoader());

        $this->assertTrue($index->hasPointcutKind(Pointcut::KIND_INIT));
        $this->assertTrue($index->hasPointcutKind(Pointcut::KIND_CLASS));
        $this->assertFalse($index->hasPointcutKind(Pointcut::KIND_FUNCTION));
        $this->assertFalse($index->hasPointcutKind(Pointcut::KIND_METHOD));
    }

    public function testCollectsPointcutKindsAgainAfterRegistration(): void
    {
        $container = new Container();
        $container->add('advisor.method', $this->createAdvisor(Pointcut::KIND_METHOD));
        $index = new AdvisorIndex($container, $this->createLoader());
        $this->assertFalse($index->hasPointcutKind(Pointcut::KIND_FUNCTION));

        $container->add('advisor.function', $this->createAdvisor(Pointcut::KIND_FUNCTION));

        $this->assertTrue($index->hasPointcutKind(Pointcut::KIND_FUNCTION));
        $this->assertTrue($index->hasPointcutKind(Pointcut::KIND_METHOD));
    }

    /**
     * Aspects registered lazily are loaded by the first query, so their pointcuts are known before the weaving
     */
    public function testLoadsUnloadedAspectsBeforeCollectingPointcutKinds(): void
    {
        $container = new Container();
        $aspect    = new class implements Aspect {};
        $advisor   = $this->createAdvisor(Pointcut::KIND_INIT | Pointcut::KIND_CLASS);
        $loader    = new class ($container, $aspect::class, $advisor) implements AspectLoaderInterface {
            private bool $isLoaded = false;

            public function __construct(
                private readonly AspectContainer $container,
                /** @var class-string<Aspect> */
                private readonly string $aspectClass,
                private readonly GenericPointcutAdvisor $advisor,
            ) {}

            public function load(string $aspectClass): array
            {
                return ['advisor.lazy' => $this->advisor];
            }

            public function loadAndRegister(string $aspectClass): void
            {
                foreach ($this->load($aspectClass) as $itemId => $item) {
                    $this->container->add($itemId, $item);
                }
                $this->isLoaded = true;
            }

            public function getUnloadedAspects(): array
            {
                return $this->isLoaded ? [] : [$this->aspectClass];
            }
        };
        $index = new AdvisorIndex($container, $loader);

        $this->assertTrue($index->hasPointcutKind(Pointcut::KIND_INIT));
        $this->assertSame(['advisor.lazy' => $advisor], $index->getAdvisors());
    }

    /**
     * Weaving reads aspects by class: even an aspect PHP can not make lazy (no instance properties) is not created,
     * its factory runs on the first call of one of its advices only
     */
    public function testLoadsDeferredAspectsWithoutRunningTheirFactories(): void
    {
        $container = new Container();
        $container->addLazyService(
            AdvisorIndexTestStatelessAspect::class,
            static fn(): never => throw new LogicException('Weaving must not create the aspect'),
        );
        $loader = new AspectLoader(
            $container,
            new AttributeAspectLoaderExtension(new PointcutLexer(), new PointcutParser(new PointcutGrammar())),
        );
        $index = new AdvisorIndex($container, $loader);

        $this->assertTrue($index->hasPointcutKind(Pointcut::KIND_METHOD));
        $this->assertArrayHasKey(AdvisorIndexTestStatelessAspect::class . '->beforeMethod', $index->getAdvisors());
    }

    private function createAdvisor(int $pointcutKind): GenericPointcutAdvisor
    {
        return new GenericPointcutAdvisor(new TruePointcut($pointcutKind), $this->createStub(Advice::class));
    }

    private function createLoader(): AspectLoaderInterface
    {
        $loader = $this->createStub(AspectLoaderInterface::class);
        $loader->method('getUnloadedAspects')->willReturn([]);

        return $loader;
    }
}

final class AdvisorIndexTestStatelessAspect implements Aspect
{
    #[Before('execution(public NonExistent\\**->*(*))')]
    public function beforeMethod(): void {}
}
