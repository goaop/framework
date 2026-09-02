<?php

declare(strict_types=1);

namespace Go\Aop\Framework;

use Closure;
use Go\Aop\Advice;
use Go\Aop\AdviceTypeEnum;
use Go\Aop\Advisor;
use Go\Aop\Aspect;
use Go\Aop\AspectException;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\Container;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;

final class TheTest extends TestCase
{
    protected function tearDown(): void
    {
        $instance = new ReflectionProperty(AspectKernel::class, 'instance');
        $instance->setValue(null, null);
    }

    public function testReturnsRegisteredAspectInstance(): void
    {
        $this->initKernelWithContainerValues([]);

        // @phpstan-ignore method.alreadyNarrowedType (runtime double-check of the declared return type)
        $this->assertInstanceOf(TheTestAspect::class, The::aspect(TheTestAspect::class));
    }

    public function testReturnsAdviceClosureFromAdvisor(): void
    {
        $this->initKernelWithContainerValues([
            'manual-advisor' => new class implements Advisor {
                public Closure $advice;

                public function __construct()
                {
                    $this->advice = static function (): void {};
                }

                public function getAdvice(): Advice
                {
                    return new AroundInterceptor($this->advice);
                }
            },
        ]);

        // @phpstan-ignore method.alreadyNarrowedType (runtime double-check of the declared return type)
        $this->assertInstanceOf(Closure::class, The::advice('manual-advisor'));
    }

    public function testReturnsAdviceClosureFromDirectInterceptor(): void
    {
        $advice = static function (): void {};
        $this->initKernelWithContainerValues([
            'manual-interceptor' => new BeforeInterceptor($advice),
        ]);

        $this->assertSame($advice, The::advice('manual-interceptor'));
    }

    public function testReturnsDirectClosureAdvisor(): void
    {
        $advice = static function (): void {};
        $this->initKernelWithContainerValues([
            'manual-closure' => $advice,
        ]);

        $this->assertSame($advice, The::advice('manual-closure'));
    }

    public function testFailsForMissingAdvisor(): void
    {
        $this->initKernelWithContainerValues([]);

        $this->expectException(OutOfBoundsException::class);

        The::advice('missing');
    }

    public function testFailsForUnsupportedAdvisorValue(): void
    {
        $this->initKernelWithContainerValues([
            'manual-value' => new stdClass(),
        ]);

        $this->expectException(AspectException::class);
        $this->expectExceptionMessage('does not expose a closure advice');

        The::advice('manual-value');
    }

    public function testFailsForUnsupportedAdvisorAdvice(): void
    {
        $this->initKernelWithContainerValues([
            'manual-advisor' => new class implements Advisor {
                public function getAdvice(): Advice
                {
                    return new class implements Advice {
                        public function getType(): AdviceTypeEnum
                        {
                            return AdviceTypeEnum::Before;
                        }
                    };
                }
            },
        ]);

        $this->expectException(AspectException::class);
        $this->expectExceptionMessage('does not expose a closure advice');

        The::advice('manual-advisor');
    }

    /**
     * @param array<string, mixed> $values
     */
    private function initKernelWithContainerValues(array $values): void
    {
        $kernel = TheTestAspectKernel::getInstance();
        $container = new Container();
        $container->registerAspect(new TheTestAspect());
        foreach ($values as $id => $value) {
            $container->add($id, $value);
        }

        $containerProperty = new ReflectionProperty(AspectKernel::class, 'container');
        $containerProperty->setValue($kernel, $container);
    }
}

final class TheTestAspectKernel extends AspectKernel
{
    protected function configureAop(AspectContainer $container): void
    {
        $container->registerAspect(new TheTestAspect());
    }
}

final class TheTestAspect implements Aspect {}
