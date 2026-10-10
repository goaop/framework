<?php
declare(strict_types = 1);

namespace Go\Tests\TestProject\Kernel;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Tests\TestProject\Aspect\ZEngineAspect;
use Go\Tests\TestProject\Aspect\ZEngineUnsupportedAspect;

/**
 * Kernel registering an aspect with a join point kind the z-engine driver refuses
 */
class ZEngineUnsupportedAspectKernel extends AspectKernel
{
    protected function configureAop(AspectContainer $container): void
    {
        $container->addLazyService(ZEngineAspect::class, fn(): ZEngineAspect => new ZEngineAspect());
        $container->addLazyService(ZEngineUnsupportedAspect::class, fn(): ZEngineUnsupportedAspect => new ZEngineUnsupportedAspect());
    }
}
