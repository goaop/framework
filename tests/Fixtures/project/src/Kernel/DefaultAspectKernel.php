<?php
declare(strict_types = 1);

namespace Go\Tests\TestProject\Kernel;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Tests\TestProject\Aspect\ArrayPropertyInterceptAspect;
use Go\Tests\TestProject\Aspect\DoSomethingAspect;
use Go\Tests\TestProject\Aspect\EnumMethodAspect;
use Go\Tests\TestProject\Aspect\InitializationAspect;
use Go\Tests\TestProject\Aspect\Issue293Aspect;
use Go\Tests\TestProject\Aspect\LoggingAspect;
use Go\Tests\TestProject\Aspect\PromotedPropertyInterceptAspect;
use Go\Tests\TestProject\Aspect\PropertyInterceptAspect;
use Go\Tests\TestProject\Aspect\TraitCompositionAspect;
use Go\Tests\TestProject\Aspect\WeavingAspect;
use Psr\Log\NullLogger;

class DefaultAspectKernel extends AspectKernel
{
    /**
     * {@inheritdoc}
     */
    protected function configureAop(AspectContainer $container): void
    {
        $container->registerAspect(LoggingAspect::class, fn(): LoggingAspect => new LoggingAspect(new NullLogger()));
        $container->registerAspect(DoSomethingAspect::class);
        $container->registerAspect(ArrayPropertyInterceptAspect::class);
        $container->registerAspect(PropertyInterceptAspect::class);
        $container->registerAspect(PromotedPropertyInterceptAspect::class);
        $container->registerAspect(Issue293Aspect::class);
        $container->registerAspect(InitializationAspect::class);
        $container->registerAspect(WeavingAspect::class);
        $container->registerAspect(TraitCompositionAspect::class);
        $container->registerAspect(EnumMethodAspect::class);
    }
}
