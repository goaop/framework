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
 * Weaving input for a class importing a trait method with #[\Override]: the attribute is declared in the trait file,
 * the token stream of this file must not be touched for it, and the interception of `count` is rejected (#761).
 */
class ClassUsingOverrideTrait implements \Countable
{
    use OverrideTrait;

    public function ownMethod(): string
    {
        return 'own';
    }
}
