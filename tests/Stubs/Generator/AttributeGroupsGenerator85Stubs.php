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

// PHP 8.5 syntax: closures and first-class callables in constant expressions.

namespace Go\Stubs\Generator;

#[TestRichAttr(strlen(...))]
function attrGenHelper85_fccArg(): void {}

#[TestRichAttr(static function (int $x): int { return $x * 2; })]
function attrGenHelper85_closureArg(): void {}
