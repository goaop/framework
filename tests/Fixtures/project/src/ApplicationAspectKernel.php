<?php
declare(strict_types = 1);

namespace Go\Tests\TestProject;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Tests\TestProject\Aspect\DoSomethingAspect;
use Go\Tests\TestProject\Aspect\LoggingAspect;
use Psr\Log\NullLogger;

class ApplicationAspectKernel extends AspectKernel
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
    }
}
