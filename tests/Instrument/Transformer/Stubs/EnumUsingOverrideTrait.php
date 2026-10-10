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
 * Weaving input for an enum importing a trait method with #[\Override]: the enum proxy aliases every intercepted
 * method, so the interception of `count` is rejected (issue #761)
 */
enum EnumUsingOverrideTrait implements \Countable
{
    use OverrideTrait;

    case First;
}
