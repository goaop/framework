<?php

declare(strict_types=1);

namespace Go\Stubs\ZEngine;

/**
 * Woven from a donor record the test wrote before the kernel booted: the warm path of the weaver
 */
final class WarmTarget
{
    public function answer(): int
    {
        return 42;
    }
}
