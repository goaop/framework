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
 * Detailed information about the intercepted field access can be obtained via {@see self::getField()} method which
 * returns {@see ReflectionProperty} instance of relevant field.
 *
 * This interface is declared as generic, to get better code completion, specify concrete generic type for
 * your parameter as `FieldAccess<SomeConcreteType>` in your aspects to make {@see self::getThis()} method
 * returning proper type for instance `SomeConcreteType`. Same applied to the {@see self::getScope()} method -
 * it will return proper type for instance `SomeConcreteType`.
 *
 * Interface overrides the return type of {@see ClassJoinpoint::getThis()} method and narrows its return type to
 * the generic object for all field accesses, removing the nullability of the return type.
 *
 * @api
 *
 * @template T of object = object
 * @extends ClassJoinpoint<T>
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

    /**
     * @phpstan-return T Covariant, always instance of object, can not be null
     */
    public function getThis(): object;

    /**
     * @return true Covariance, always true for class properties
     */
    public function isDynamic(): true;
}
