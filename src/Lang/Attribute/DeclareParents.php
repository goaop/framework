<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2024, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Lang\Attribute;

use Attribute;

/**
 * Declare parents attribute
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class DeclareParents extends AbstractAttribute
{
    public function __construct(
        string $expression,
        /** @var class-string Interface name to add */
        public readonly string $interfaceName,
        /** @var trait-string Default implementation (trait name) */
        public readonly string $traitName,
        int $order = 0,
    ) {
        parent::__construct($expression, $order);
    }
}
