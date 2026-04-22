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

namespace Go\Aop\Framework;

use Go\Aop\AspectException;
use Go\Aop\Intercept\FieldAccess;
use Go\Aop\Intercept\FieldAccessType;
use Go\Aop\Intercept\Interceptor;
use ReflectionProperty;

/**
 * Represents a field access joinpoint
 *
 * @template T of object = object
 * @template V of mixed = mixed
 * @implements FieldAccess<T,V>
 */
final class ClassFieldAccess extends AbstractJoinpoint implements FieldAccess
{
    /**
     * Mapping of access types to property names
     *
     * @var array<key-of<FieldAccessType>, string> $propertyMap
     */
    private static array $propertyMap = [
        FieldAccessType::READ->name => 'value',
        FieldAccessType::WRITE->name => 'newValue',
    ];

    /**
     * Instance of object for accessing
     * @phpstan-var T
     */
    private object $instance;

    /**
     * Instance of reflection property
     */
    private readonly ReflectionProperty $reflectionProperty;

    /**
     * Reference to the original value of property
     *
     * Maybe uninitialized if the property itself is not initialized yet
     *
     * @phpstan-var V Templated type
     */
    private mixed $value;

    /**
     * New value to set
     *
     * Maybe uninitialized if set hook has not called yet
     *
     * @phpstan-var V Templated type
     */
    private mixed $newValue;

    /**
     * Access type for field access
     */
    private FieldAccessType $accessType;

    /**
     * Constructor for field access
     *
     * @param array<Interceptor> $advices List of advices for this invocation
     * @param class-string<T> $className
     */
    public function __construct(array $advices, string $className, string $fieldName)
    {
        parent::__construct($advices);
        $this->reflectionProperty = new ReflectionProperty($className, $fieldName);
    }

    public function getAccessType(): FieldAccessType
    {
        return $this->accessType;
    }

    public function getField(): ReflectionProperty
    {
        return $this->reflectionProperty;
    }

    /**
     * Gets the current value of property
     *
     * @return V
     */
    public function getValue(): mixed
    {
        if (!$this->reflectionProperty->isInitialized($this->instance)) {
            throw new AspectException("Property {$this->reflectionProperty->name} is not initialized yet");
        }
        // We can not use ReflectionProperty->getValue() here, as it will call again the hook
        return $this->value;
    }

    /**
     * Gets the value that must be set to the field, applicable only for WRITE access type
     *
     * @return V
     */
    public function getValueToSet(): mixed
    {
        if ($this->accessType === FieldAccessType::READ) {
            throw new AspectException("Value to set is not available for READ access type");
        }
        return $this->newValue;
    }

    final public function proceed(): mixed
    {
        if (isset($this->advices[$this->current])) {
            $currentInterceptor = $this->advices[$this->current++];

            return $currentInterceptor->invoke($this);
        }

        // Next line can cause an Error if the property is not initialized yet
        // To prevent this error, use Around hook or ensure that underlying property is initialized
        return $this->{self::$propertyMap[$this->accessType->name]};
    }

    /**
     * Invokes current field access with all interceptors
     *
     * @phpstan-param T $instance Instance of object for accessing
     * @param FieldAccessType $accessType Access type for field access
     * @phpstan-param V ...$values Original value of property + new value (for write operation)
     *
     * @phpstan-return V Templated return type of property
     */
    final public function &__invoke(object $instance, FieldAccessType $accessType, mixed &...$values): mixed
    {
        $this->current    = 0;
        $this->instance   = $instance;
        $this->accessType = $accessType;
        unset($this->value, $this->newValue);

        // $values[0] - either we have a reference to the original property for READ
        // OR reference to the new value for WRITE.
        // Can be unset only for READ operation when property is not initialized yet
        if (isset($values[0])) {
            $this->{self::$propertyMap[$accessType->name]} = &$values[0];
        }
        // $values[1] - either we have a reference to the original property for WRITE
        // OR can be unset for WRITE operation when property is not initialized yet
        if (isset($values[1])) {
            $this->value = &$values[1];
        }

        $this->{self::$propertyMap[$accessType->name]} = $this->proceed();

        return $this->{self::$propertyMap[$accessType->name]};
    }

    final public function getThis(): object
    {
        return $this->instance;
    }

    final public function isDynamic(): true
    {
        return true;
    }

    final public function getScope(): string
    {
        return $this->instance::class;
    }

    /**
     * Returns a friendly description of current joinpoint
     */
    final public function __toString(): string
    {
        return sprintf(
            '%s(%s->%s)',
            $this->accessType->value,
            $this->getScope(),
            $this->reflectionProperty->name
        );
    }
}
