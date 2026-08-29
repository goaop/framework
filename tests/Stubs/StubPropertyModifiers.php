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
 * Stub class with readonly and asymmetric-visibility properties, used to test
 * the 'readonly', 'private(set)' and 'protected(set)' pointcut modifier predicates.
 */
class StubPropertyModifiers
{
    public string $plain = '';

    public readonly int $readonlyProp;

    public private(set) string $privateSetProp = '';

    public protected(set) string $protectedSetProp = '';

    public function __construct()
    {
        $this->readonlyProp = 1;
    }
}
