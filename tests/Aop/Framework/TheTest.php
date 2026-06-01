<?php

declare(strict_types=1);

namespace Go\Aop\Framework;

use Go\Aop\Aspect;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\Container;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

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

        $this->assertInstanceOf(TheTestAspect::class, The::aspect(TheTestAspect::class));
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

final class TheTestAspect implements Aspect
{
}
