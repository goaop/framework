<?php
declare(strict_types = 1);

namespace Go\Tests\TestProject\Kernel;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Tests\TestProject\Aspect\FfiAspect;
use Go\Tests\TestProject\Aspect\FfiUnsupportedAspect;

/**
 * Kernel registering an aspect with a join point kind the ffi driver refuses
 */
class FfiUnsupportedAspectKernel extends AspectKernel
{
    protected function configureAop(AspectContainer $container): void
    {
        $container->addLazyService(FfiAspect::class, fn(): FfiAspect => new FfiAspect());
        $container->addLazyService(FfiUnsupportedAspect::class, fn(): FfiUnsupportedAspect => new FfiUnsupportedAspect());
    }
}
