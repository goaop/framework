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
 * Declares error attribute
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class DeclareError extends AbstractAttribute
{
    /**
     * @inheritdoc
     * @param int&(\E_USER_NOTICE|\E_USER_WARNING|\E_USER_ERROR|\E_USER_DEPRECATED) $level Default level of error, only E_USER_* constants
     */
    public function __construct(
        string              $expression,
        readonly public int $level = E_USER_NOTICE,
        int                 $order = 0,
    ) {
        parent::__construct($expression, $order);
    }
}
