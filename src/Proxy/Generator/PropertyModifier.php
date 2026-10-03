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

namespace Go\Proxy\Generator;

/**
 * Modifier of a generated class property declaration.
 *
 * A set of modifiers is represented as a list of enum cases; the backing
 * values keep the historical single-bit mask values for reference.
 */
enum PropertyModifier: int
{
    case Public        = 0b0001;
    case Protected     = 0b0010;
    case Private       = 0b0100;
    case Static        = 0b1000;
    case Readonly      = 0b0001_0000;
    case ProtectedSet = 0b0010_0000;
    case PrivateSet   = 0b0100_0000;
    case Final         = 0b1000_0000;
}
