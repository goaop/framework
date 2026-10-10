<?php

declare(strict_types=1);

namespace Go\Stubs\ZEngine;

/**
 * Woven from native reflection: the weaver is handed a source file the parser cannot read
 */
final class FallbackTarget
{
    public function twice(int $value): int
    {
        return $value * 2;
    }
}
