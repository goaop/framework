<?php

declare(strict_types=1);

namespace Go\Stubs\Driver;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Driver\WeavingDriver;

/**
 * A weaving driver that does not transform sources (nothing to warm up, no transformer chain): boots to nothing
 */
final class RewiringDriver implements WeavingDriver
{
    public static int $boots = 0;

    public function boot(AspectKernel $kernel, AspectContainer $container): void
    {
        self::$boots++;
    }
}
