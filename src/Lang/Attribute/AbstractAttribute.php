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

use BadMethodCallException;

abstract class AbstractAttribute
{
    /**
     * @param string $expression Advice pointcut expression
     * @param int $order         Order for advice/interceptor (used for sorting)
     */
    public function __construct(
        readonly public string $expression = '',
        readonly public int    $order = 0,
    ) {}

    /**
     * Error handler for unknown property accessor in attribute class.
     */
    public function __get(string $name): never
    {
        throw new BadMethodCallException(
            sprintf("Unknown property '%s' on attribute '%s'.", $name, static::class)
        );
    }

    /**
     * Error handler for unknown property mutator in attribute class.
     *
     * @param mixed $value Property value
     */
    public function __set(string $name, mixed $value): never
    {
        throw new BadMethodCallException(
            sprintf("Unknown property '%s' on attribute '%s'.", $name, static::class)
        );
    }
}
