<?php
declare(strict_types = 1);

namespace Go\Tests\TestProject\Kernel;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Tests\TestProject\Aspect\Alpha\CollisionAspect as AlphaCollisionAspect;
use Go\Tests\TestProject\Aspect\ArrayPropertyInterceptAspect;
use Go\Tests\TestProject\Aspect\Beta\CollisionAspect as BetaCollisionAspect;
use Go\Tests\TestProject\Aspect\DoSomethingAspect;
use Go\Tests\TestProject\Aspect\EdgeCaseAspect;
use Go\Tests\TestProject\Aspect\EntityFieldAspect;
use Go\Tests\TestProject\Aspect\EnumMethodAspect;
use Go\Tests\TestProject\Aspect\InitializationAspect;
use Go\Tests\TestProject\Aspect\Issue293Aspect;
use Go\Tests\TestProject\Aspect\LoggingAspect;
use Go\Tests\TestProject\Aspect\NullablePropertyInterceptAspect;
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
        $container->addLazyService(LoggingAspect::class, fn(): LoggingAspect => new LoggingAspect(new NullLogger()));
        $container->addLazyService(DoSomethingAspect::class, fn(): DoSomethingAspect => new DoSomethingAspect());
        $container->addLazyService(ArrayPropertyInterceptAspect::class, fn(): ArrayPropertyInterceptAspect => new ArrayPropertyInterceptAspect());
        $container->addLazyService(NullablePropertyInterceptAspect::class, fn(): NullablePropertyInterceptAspect => new NullablePropertyInterceptAspect());
        $container->addLazyService(PropertyInterceptAspect::class, fn(): PropertyInterceptAspect => new PropertyInterceptAspect());
        $container->addLazyService(PromotedPropertyInterceptAspect::class, fn(): PromotedPropertyInterceptAspect => new PromotedPropertyInterceptAspect());
        $container->addLazyService(Issue293Aspect::class, fn(): Issue293Aspect => new Issue293Aspect());
        $container->addLazyService(InitializationAspect::class, fn(): InitializationAspect => new InitializationAspect());
        $container->addLazyService(WeavingAspect::class, fn(): WeavingAspect => new WeavingAspect());
        $container->addLazyService(TraitCompositionAspect::class, fn(): TraitCompositionAspect => new TraitCompositionAspect());
        $container->addLazyService(EnumMethodAspect::class, fn(): EnumMethodAspect => new EnumMethodAspect());
        $container->addLazyService(EntityFieldAspect::class, fn(): EntityFieldAspect => new EntityFieldAspect());
        $container->addLazyService(AlphaCollisionAspect::class, fn(): AlphaCollisionAspect => new AlphaCollisionAspect());
        $container->addLazyService(BetaCollisionAspect::class, fn(): BetaCollisionAspect => new BetaCollisionAspect());
        $container->addLazyService(EdgeCaseAspect::class, fn(): EdgeCaseAspect => new EdgeCaseAspect());
    }
}
