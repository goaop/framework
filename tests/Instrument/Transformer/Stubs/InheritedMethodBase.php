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
 * Declares the method that InheritedMethodChild only inherits — used to check that an
 * advice on an inherited method is dispatched through `parent::method(...)` instead of a
 * trait alias, and that no token surgery is attempted on the child's woven trait for it.
 */
class InheritedMethodBase
{
    public string $inheritedProperty = 'base';

    public function inheritedMethod(): string
    {
        return 'base';
    }
}
