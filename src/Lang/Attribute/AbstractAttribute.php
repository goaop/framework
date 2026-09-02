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

abstract class AbstractAttribute
{
    /**
     * @param string $expression Advice pointcut expression
     * @param int $order         Order for advice/interceptor (used for sorting)
     */
    public function __construct(
        public readonly string $expression = '',
        public readonly int    $order = 0,
    ) {}
}
