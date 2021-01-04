<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2018, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Stubs\Constructor;

use stdClass;

class ClassWithOptionalArgsConstructor
{
    public function __construct(int $foo = 42, bool $bar = false, stdClass $instance = null)
    {
    }
}
