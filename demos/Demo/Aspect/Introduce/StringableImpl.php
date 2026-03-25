<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Demo\Aspect\Introduce;

/**
 * Trait that provides a default __toString() implementation, introduced via AOP.
 */
trait StringableImpl
{
    /**
     * Returns a string representation of the object based on its public properties.
     */
    public function __toString(): string
    {
        return static::class . '(' . implode(', ', array_map(
            static fn(string $k, mixed $v): string => "$k=" . var_export($v, true),
            array_keys(get_object_vars($this)),
            get_object_vars($this)
        )) . ')';
    }
}
