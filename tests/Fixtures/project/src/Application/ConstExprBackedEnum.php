<?php

declare(strict_types=1);

namespace Go\Tests\TestProject\Application;

/**
 * Backed enum whose case values are constant expressions instead of plain literals (issue #600).
 *
 * Used by EnumWeavingTest to verify that the generated proxy enum re-declares these cases with
 * their original expressions (dropping them would declare pure cases in a backed enum — fatal).
 */
enum ConstExprBackedEnum: int
{
    private const int SHIFT = 2;

    case Negative  = -1;
    case Shifted   = 1 << 2;
    case FromConst = self::SHIFT + 10;

    public function describe(): string
    {
        return $this->name . '=' . $this->value;
    }
}
