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

namespace Go\Instrument\Transformer\Stubs;

/**
 * Plain class instantiated through ConstructorExecutionTransformer's magic interceptors.
 */
class ConstructedStub
{
    public function __construct(public string $name = 'default', public int $size = 0)
    {
    }
}
