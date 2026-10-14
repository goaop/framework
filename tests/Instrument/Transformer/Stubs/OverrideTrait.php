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
 * Trait method with #[\Override], imported by {@see ClassUsingOverrideTrait}: its attribute lives in this file.
 * The `label` method carries another attribute only, so it can be intercepted.
 */
trait OverrideTrait
{
    #[\Override]
    public function count(): int
    {
        return 0;
    }

    #[\ReturnTypeWillChange]
    public function label(): string
    {
        return 'label';
    }
}
