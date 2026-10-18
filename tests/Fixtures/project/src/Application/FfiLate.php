<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Application;

/**
 * Declared BEFORE the aspect kernel is initialized (required directly, never autoloaded): woven on demand
 * through ClassWeaver::weave()
 */
final class FfiLate
{
    public function compute(int $left, int $right): int
    {
        return $left + $right;
    }
}
