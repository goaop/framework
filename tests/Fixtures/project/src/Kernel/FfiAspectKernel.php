<?php
declare(strict_types = 1);

namespace Go\Tests\TestProject\Kernel;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Tests\TestProject\Aspect\FfiAspect;

/**
 * Kernel of the ffi driver fixtures: method-execution advices only
 */
class FfiAspectKernel extends AspectKernel
{
    protected function configureAop(AspectContainer $container): void
    {
        $container->addLazyService(FfiAspect::class, fn(): FfiAspect => new FfiAspect());
    }
}
