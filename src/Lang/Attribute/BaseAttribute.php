<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Lang\Attribute;

use Attribute;

#[Attribute]
abstract class BaseAttribute
{
    /**
     * Value property. Common among all derived classes.
     */
    public ?string $value;

    /**
     * BaseAttribute Constructor
     *
     * @param string|null $value
     */
    public function __construct(string $value = null)
    {
        $this->value = $value;
    }
}
