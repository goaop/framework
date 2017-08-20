<?php

namespace Go\Tests\TestProject\Kernel;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Tests\TestProject\Aspect\DoSomethingAspect;
use Go\Tests\TestProject\Aspect\Issue293Aspect;
use Go\Tests\TestProject\Aspect\LoggingAspect;
use Go\Tests\TestProject\Aspect\PropertyInterceptAspect;
use Psr\Log\NullLogger;

class DefaultAspectKernel extends AspectKernel
{
    /**
     * Configure an AspectContainer with advisors, aspects and pointcuts
     *
     * @param AspectContainer $container
     *
     * @return void
     */
    protected function configureAop(AspectContainer $container)
    {
        $container->registerAspect(new LoggingAspect(new NullLogger()));
        $container->registerAspect(new DoSomethingAspect());
        $container->registerAspect(new PropertyInterceptAspect());
        $container->registerAspect(new Issue293Aspect());
    }
}
