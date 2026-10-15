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
use Go\Aop\Pointcut\TruePointcut;
use Go\Aop\Support\GenericPointcutAdvisor;
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
        $loader    = new class ($container, $aspect, $advisor) implements AspectLoaderInterface {
            private bool $isLoaded = false;

            public function __construct(
                private readonly AspectContainer $container,
                private readonly Aspect $aspect,
                private readonly GenericPointcutAdvisor $advisor,
            ) {}

            public function load(Aspect $aspect): array
            {
                return ['advisor.lazy' => $this->advisor];
            }

            public function loadAndRegister(Aspect $aspect): void
            {
                foreach ($this->load($aspect) as $itemId => $item) {
                    $this->container->add($itemId, $item);
                }
                $this->isLoaded = true;
            }

            public function getUnloadedAspects(): array
            {
                return $this->isLoaded ? [] : [$this->aspect];
            }
        };
        $index = new AdvisorIndex($container, $loader);

        $this->assertTrue($index->hasPointcutKind(Pointcut::KIND_INIT));
        $this->assertSame(['advisor.lazy' => $advisor], $index->getAdvisors());
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
