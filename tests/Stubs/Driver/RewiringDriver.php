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

    /**
     * Whether the kernel had registered the driver in the container when boot() ran
     */
    public static bool $registeredWhenBooted = false;

    public static function reset(): void
    {
        self::$boots               = 0;
        self::$registeredWhenBooted = false;
    }

    public function boot(AspectKernel $kernel, AspectContainer $container): void
    {
        self::$boots++;
        self::$registeredWhenBooted = $container->has(WeavingDriver::class) && $container->get(WeavingDriver::class) === $this;
    }
}
