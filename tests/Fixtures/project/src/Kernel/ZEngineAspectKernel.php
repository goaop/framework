<?php
declare(strict_types = 1);

namespace Go\Tests\TestProject\Kernel;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Tests\TestProject\Aspect\ZEngineAspect;

/**
 * Kernel of the z-engine driver fixtures: method-execution advices only
 */
class ZEngineAspectKernel extends AspectKernel
{
    protected function configureAop(AspectContainer $container): void
    {
        $container->addLazyService(ZEngineAspect::class, fn(): ZEngineAspect => new ZEngineAspect());
    }
}
