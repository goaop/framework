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
 * Weaving input for an advice that matches a method inherited from the parent class:
 * the inheritance clause has to move from the woven trait to the proxy class, and the
 * inherited method is dispatched through a `parent::` first-class callable.
 */
class InheritedMethodChild extends InheritedMethodBase
{
    public function ownMethod(): string
    {
        return 'child';
    }
}
