<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Stubs;

/**
 * Stub backed enum with constant-expression case values (issue #600),
 * used by EnumProxyGeneratorTest.
 */
enum StubConstExprBackedEnum: int
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
