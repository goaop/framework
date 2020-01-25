<?php
declare(strict_types = 1);

namespace Go\Tests\TestProject\Kernel;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Tests\TestProject\Aspect\DoSomethingAspect;
use Go\Tests\TestProject\Aspect\InitializationAspect;
use Go\Tests\TestProject\Aspect\Issue293Aspect;
use Go\Tests\TestProject\Aspect\LoggingAspect;
use Go\Tests\TestProject\Aspect\PropertyInterceptAspect;
use Go\Tests\TestProject\Aspect\WeavingAspect;
use Psr\Log\NullLogger;

class DefaultAspectKernel extends AspectKernel
{
    /**
     * {@inheritdoc}
     */
    protected function configureAop(AspectContainer $container)
    {
        $container->registerAspect(new LoggingAspect(new NullLogger()));
        $container->registerAspect(new DoSomethingAspect());
        $container->registerAspect(new PropertyInterceptAspect());
        $container->registerAspect(new Issue293Aspect());
        $container->registerAspect(new InitializationAspect());
        $container->registerAspect(new WeavingAspect());
    }
}
