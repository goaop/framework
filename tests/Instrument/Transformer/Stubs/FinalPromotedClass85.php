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
 * This file uses the PHP 8.5+ `final` promoted constructor property syntax and must
 * only be loaded on PHP >= 8.5. Tests referencing it are gated with #[RequiresPhp].
 *
 * Used for testing demotion of intercepted promoted properties (issue #599).
 */
class FinalPromotedClass85
{
    public function __construct(final public string $token = 'secret') {}
}
