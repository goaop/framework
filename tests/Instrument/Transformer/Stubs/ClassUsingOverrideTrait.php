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
 * Weaving input for an advice that matches a method imported from a trait with #[\Override]: the attribute is
 * declared in the trait file, the token stream of this file must not be touched for it.
 */
class ClassUsingOverrideTrait implements \Countable
{
    use OverrideTrait;

    public function ownMethod(): string
    {
        return 'own';
    }
}
