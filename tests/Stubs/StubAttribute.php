<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2024, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Stubs;

use Attribute;

#[Attribute(flags: Attribute::TARGET_ALL)]
readonly class StubAttribute
{
    public function __construct(public string $value) {}
}
