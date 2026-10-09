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

use Override as OverrideAlias;

/**
 * Trait weaving input with #[\Override] on its own methods: the attribute must be stripped from the intercepted
 * methods of the renamed original trait, as the proxy trait aliases them (issue #761)
 */
trait WovenOverrideTrait
{
    #[\Override]
    public function hello(): string
    {
        return 'hello';
    }

    #[OverrideAlias]
    public static function make(): string
    {
        return 'make';
    }

    #[\ReturnTypeWillChange, \Override]
    public function count(): int
    {
        return 0;
    }

    #[\Override]
    public function notIntercepted(): string
    {
        return 'kept';
    }
}
