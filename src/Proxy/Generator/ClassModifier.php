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
 * Modifier of a generated class declaration.
 *
 * A set of modifiers is represented as a list of enum cases; the backing
 * values keep the historical single-bit mask values for reference.
 */
enum ClassModifier: int
{
    case FINAL    = 0b001;
    case ABSTRACT = 0b010;
    case READONLY = 0b100;
}
