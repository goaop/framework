<?php

declare(strict_types=1);

namespace Go\Stubs\ZEngine;

/**
 * Loaded by a plain require before the kernel exists, woven on demand through ZEngineClassWeaver::weave()
 */
final class LateTarget
{
    public function compute(int $left, int $right): int
    {
        return $left + $right;
    }
}
