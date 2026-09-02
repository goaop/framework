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
    case PUBLIC        = 0b0001;
    case PROTECTED     = 0b0010;
    case PRIVATE       = 0b0100;
    case STATIC        = 0b1000;
    case READONLY      = 0b0001_0000;
    case PROTECTED_SET = 0b0010_0000;
    case PRIVATE_SET   = 0b0100_0000;
    case FINAL         = 0b1000_0000;
}
