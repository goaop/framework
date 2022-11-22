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

/**
 * Declare parents attribute
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class DeclareParents extends BaseAttribute
{
    /**
     * Default implementation (trait name)
     */
    public string $defaultImpl;

    /**
     * Interface name to add
     */
    public string $interface;

    /**
     * DeclareParents constructor
     *
     * @param string $value
     * @param string $interface
     * @param string $defaultImpl
     */
    public function __construct(string $value, string $interface, string $defaultImpl)
    {
        parent::__construct($value);
        $this->interface = $interface;
        $this->defaultImpl = $defaultImpl;
    }
}
