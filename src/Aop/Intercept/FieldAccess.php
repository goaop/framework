<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Intercept;

use ReflectionProperty;

/**
 * This interface represents a field access in the program.
 *
 * A field access is a joinpoint and can be intercepted by a field interceptor.
 *
 * @api
 */
interface FieldAccess extends ClassJoinpoint
{
    /**
     * Gets the field being accessed.
     *
     * @api
     */
    public function getField(): ReflectionProperty;

    /**
     * Gets the current value of property by reference
     *
     * @api
     */
    public function &getValue(): mixed;

    /**
     * Gets the value that must be set to the field, applicable only for WRITE access type
     *
     * @api
     */
    public function &getValueToSet(): mixed;

    /**
     * Returns the access type.
     *
     * @api
     */
    public function getAccessType(): FieldAccessType;
}
