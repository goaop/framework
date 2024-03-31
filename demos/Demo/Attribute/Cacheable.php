<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Demo\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
readonly class Cacheable
{
    public function __construct(public int $time) {}
}
