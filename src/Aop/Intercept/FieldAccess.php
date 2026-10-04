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

use Go\Aop\AspectException;
use ReflectionProperty;

/**
 * This interface represents a field access in the program.
 *
 * Detailed information about the intercepted field access can be obtained via {@see self::getField()} method which
 * returns {@see ReflectionProperty} instance of relevant field.
 *
 * This interface is declared as generic, to get better code completion, you can specify one or two extra template
 * types for your parameter:
 *
 *  - First optional template parameter `<T>` is the class, which holds the property(field).
 * You can use `FieldAccess<PropertyClass>` in your aspects to make {@see self::getThis()} method
 * returning object with concrete `PropertyClass` type. The same applies to the {@see self::getScope()} method - it
 * will return the proper type for an instance of `PropertyClass`.
 *
 *  - Second optional template parameter `<V>` is the type of property.
 * You can use `FieldAccess<PropertyClass,PropertyType>` in your aspects to make {@see self::getValue()} method
 * returning object with concrete `PropertyType` type. The same applies to the {@see self::getValueToSet()} method - it
 * will return the proper type for an instance of `PropertyType`.
 *
 * If not specified, `<T>` is equal to general `object` and `<V>` is equal to general `mixed` property type.
 *
 * Native property weaving in PHP 8.4+ uses property hooks. Therefore, static properties, readonly properties and
 * properties that already declare hooks are not eligible for field access interception.
 *
 * Interface overrides the return type of {@see ClassJoinpoint::getThis()} method and narrows its return type to
 * the generic object `<T>` for all field accesses, removing the nullability of the return type.
 *
 * @api
 *
 * @template T of object = object Declares the class, which holds the property(field)
 * @template V = mixed Declares the type of property
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
     * Gets the current value of property
     *
     * @throws AspectException if original property is not initialized yet
     * @return V
     * @api
     */
    public function getValue(): mixed;

    /**
     * Gets the value that must be set to the field, applicable only for WRITE access type
     *
     * @throws AspectException if called for READ access type, check {@see self::getAccessType()}
     * @return V
     * @api
     */
    public function getValueToSet(): mixed;

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

    /**
     * Invokes the read access with all interceptors, called by the generated `get` hook
     *
     * The value is passed by value: a hook without indirect modification needs no reference, and an advice result
     * never changes the stored value.
     *
     * @phpstan-param T $instance Instance of object for accessing
     * @phpstan-param V $value Current value of the property, omitted when the property is not initialized yet
     *
     * @phpstan-return V Value returned to the reader
     */
    public function read(object $instance, mixed $value = null): mixed;

    /**
     * Invokes the read access with all interceptors and returns the property by reference
     *
     * Called by the generated `&get` hook of array properties, so indirect modifications like
     * `$object->items[] = $item` change the stored array.
     *
     * @phpstan-param T $instance Instance of object for accessing
     * @phpstan-param V $value Reference to the property, omitted when the property is not initialized yet
     *
     * @phpstan-return V Reference to the value returned to the reader
     */
    public function &readByReference(object $instance, mixed &$value = null): mixed;

    /**
     * Invokes the write access with all interceptors, called by the generated `set` hook
     *
     * @phpstan-param T $instance Instance of object for accessing
     * @phpstan-param V $newValue Value to set
     * @phpstan-param V $value Current value of the property, omitted when the property is not initialized yet
     *
     * @phpstan-return V Value to store in the property
     */
    public function write(object $instance, mixed $newValue, mixed $value = null): mixed;
}
