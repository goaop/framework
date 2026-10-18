<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop;

/**
 * Ordered advice can have a custom order to implement sorting
 */
interface OrderedAdvice extends Advice
{
    /**
     * Advice order, advices with lower order are applied first
     */
    public int $adviceOrder { get; }
}
