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
 * Trait weaving input: traits keep the legacy weaving strategy — the original trait is
 * renamed to <Name>Original in place and TraitProxyGenerator emits a child trait
 * with the original name. Intercepted properties are commented out of the original body.
 */
trait WeavingTraitStub
{
    public string $interceptedProperty = 'initial';

    protected int $plainProperty = 0;

    public function traitMethod(): string
    {
        return $this->interceptedProperty;
    }

    public static function traitStaticMethod(): int
    {
        return 42;
    }
}
