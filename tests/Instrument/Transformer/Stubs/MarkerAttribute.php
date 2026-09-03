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

use Attribute;

/**
 * Plain attribute used by weaving stubs that need attribute groups with nested brackets
 * in their arguments (constructor attributes, promoted parameter attributes).
 */
#[Attribute(Attribute::TARGET_ALL)]
final class MarkerAttribute
{
    /**
     * @param array<array-key, mixed> $payload
     */
    public function __construct(public array $payload = [])
    {
    }
}
