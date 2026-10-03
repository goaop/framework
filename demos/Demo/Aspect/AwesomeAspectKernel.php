<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Demo\Aspect;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;

/**
 * Awesome Aspect Kernel class
 */
class AwesomeAspectKernel extends AspectKernel
{
    /**
     * Configure an AspectContainer with advisors, aspects and pointcuts
     */
    protected function configureAop(AspectContainer $container): void
    {
        $container->addLazyService(CachingAspect::class, static fn(): CachingAspect => new CachingAspect());
        $container->addLazyService(LoggingAspect::class, static fn(): LoggingAspect => new LoggingAspect());
        $container->addLazyService(IntroductionAspect::class, static fn(): IntroductionAspect => new IntroductionAspect());
        $container->addLazyService(PropertyInterceptorAspect::class, static fn(): PropertyInterceptorAspect => new PropertyInterceptorAspect());
        $container->addLazyService(FunctionInterceptorAspect::class, static fn(): FunctionInterceptorAspect => new FunctionInterceptorAspect());
        $container->addLazyService(FluentInterfaceAspect::class, static fn(): FluentInterfaceAspect => new FluentInterfaceAspect());
        $container->addLazyService(HealthyLiveAspect::class, static fn(): HealthyLiveAspect => new HealthyLiveAspect());
        $container->addLazyService(DynamicMethodsAspect::class, static fn(): DynamicMethodsAspect => new DynamicMethodsAspect());
        $container->addLazyService(ErrorMonitoringAspect::class, static fn(): ErrorMonitoringAspect => new ErrorMonitoringAspect());
        $container->addLazyService(PrivateMethodAspect::class, static fn(): PrivateMethodAspect => new PrivateMethodAspect());
        $container->addLazyService(EnumInterceptorAspect::class, static fn(): EnumInterceptorAspect => new EnumInterceptorAspect());
        $container->addLazyService(PropertyHooksAspect::class, static fn(): PropertyHooksAspect => new PropertyHooksAspect());
    }
}
