<?php

declare(strict_types=1);

namespace Go\Driver\Ffi\Stubs;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;

/**
 * Kernel of the in-process ffi driver tests: created without the singleton, see InProcessTestCase
 */
final class InProcessKernel extends AspectKernel
{
    protected function configureAop(AspectContainer $container): void
    {
        $container->addLazyService(InProcessAspect::class, static fn(): InProcessAspect => new InProcessAspect());
        $container->addLazyService(UnsupportedAspect::class, static fn(): UnsupportedAspect => new UnsupportedAspect());
        $container->addLazyService(SelfMatchingAspect::class, static fn(): SelfMatchingAspect => new SelfMatchingAspect());
        $container->addLazyService(MatcherErrorAdvisor::class, static fn(): MatcherErrorAdvisor => new MatcherErrorAdvisor());
    }
}
