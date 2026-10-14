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
 * Weaving input for a trait method with #[\Override] that arrives through the parent class: the proxy calls it
 * through `parent::count(...)` without a trait alias, so it can be intercepted (issue #761)
 */
class ClassExtendingOverrideTraitUser extends ClassUsingOverrideTrait
{
    public function childMethod(): string
    {
        return 'child';
    }
}
