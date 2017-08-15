<?php

namespace Go\Tests\TestProject\Kernel;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Tests\TestProject\Application\CircularyWeaved;
use Go\Tests\TestProject\Aspect\CircularWeavingAspect;
use Go\Tests\TestProject\Aspect\LoggingAspect;
use Psr\Log\NullLogger;

class CircularWeavingAspectKernel extends AspectKernel
{
    /**
     * {@inheritdoc}
     */
    protected function configureAop(AspectContainer $container)
    {
        $container->registerAspect(new LoggingAspect(new NullLogger()));
        $container->registerAspect(new CircularWeavingAspect(new CircularyWeaved()));
    }
}
