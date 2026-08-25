<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Kernel;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Tests\TestProject\Application\InconsistentlyWeavedClass;
use Go\Tests\TestProject\Aspect\InconsistentlyWeavingAspect;
use Go\Tests\TestProject\Aspect\LoggingAspect;
use Psr\Log\NullLogger;

class InconsistentlyWeavingAspectKernel extends AspectKernel
{
    /**
     * Configure an AspectContainer with advisors, aspects and pointcuts
     *
     * @param AspectContainer $container
     *
     * @return void
     */
    protected function configureAop(AspectContainer $container): void
    {
        $container->registerAspect(LoggingAspect::class, fn(): LoggingAspect => new LoggingAspect(new NullLogger()));
        // Deliberately eager (instance) registration: the inconsistent-weaving scenario this
        // kernel exists to reproduce requires the application class to be loaded through the
        // AOP loader before weaving starts, which only happens when the aspect is constructed
        // during configureAop() rather than lazily on first use.
        $container->registerAspect(new InconsistentlyWeavingAspect(new InconsistentlyWeavedClass()));
    }
}
