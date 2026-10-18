<?php

declare(strict_types=1);

namespace Go\Driver\Ffi\Stubs;

/**
 * Loaded by a plain require before the kernel exists, woven on demand through ClassWeaver::weave()
 */
final class LateTarget
{
    public function compute(int $left, int $right): int
    {
        return $left + $right;
    }
}
