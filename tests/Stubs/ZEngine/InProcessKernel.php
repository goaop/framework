<?php

declare(strict_types=1);

namespace Go\Stubs\ZEngine;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;

/**
 * Kernel of the in-process z-engine driver tests: created without the singleton, see ZEngineInProcessTestCase
 */
final class InProcessKernel extends AspectKernel
{
    protected function configureAop(AspectContainer $container): void
    {
        $container->addLazyService(InProcessAspect::class, static fn(): InProcessAspect => new InProcessAspect());
        $container->addLazyService(UnsupportedAspect::class, static fn(): UnsupportedAspect => new UnsupportedAspect());
    }
}
