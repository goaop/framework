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
        $container->registerAspect(new DeclareErrorAspect());
        $container->registerAspect(new CachingAspect());
        $container->registerAspect(new LoggingAspect());
        $container->registerAspect(new IntroductionAspect());
        $container->registerAspect(new PropertyInterceptorAspect());
        $container->registerAspect(new FunctionInterceptorAspect());
        $container->registerAspect(new FluentInterfaceAspect());
        $container->registerAspect(new HealthyLiveAspect());
        $container->registerAspect(new DynamicMethodsAspect());
    }
}
