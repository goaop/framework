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
class DeclareParents extends AbstractAttribute
{
    /**
     * @inheritdoc
     * @param string $trait Default implementation (trait name)
     * @param string $interface Interface name to add
     */
    public function __construct(
        string                 $expression,
        readonly public string $interface,
        readonly public string $trait,
        int                    $order = 0,
    )
    {
        parent::__construct($expression, $order);
    }
}
