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
 * @template V of mixed = mixed Declares the type of property
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
     * @return V
     * @api
     */
    public function &getValue(): mixed;

    /**
     * Gets the value that must be set to the field, applicable only for WRITE access type
     *
     * @return V
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

    /**
     * Invokes current field access with all interceptors
     *
     * @phpstan-param T $instance Instance of object for accessing
     * @param FieldAccessType $accessType Access type for field access
     * @phpstan-param V ...$values Original value of property + new value by reference (for write operation)
     *
     * @phpstan-return V Templated return type of property
     */
    public function &__invoke(object $instance, FieldAccessType $accessType, mixed &...$values): mixed;
}
