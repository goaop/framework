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
     * Stack frames to work with recursive calls or with cross-calls inside object
     *
     * @var list<array{T, FieldAccessType, V, V}>
     */
    private array $stackFrames = [];

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
     * New value to set
     *
     * @phpstan-var V Templated type
     */
    private mixed $newValue;

    /**
     * Access type for field access
     */
    private FieldAccessType $accessType;

    /**
     * Copy of the original value of property
     *
     * @phpstan-var V Templated type
     */
    private mixed $value;

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
     * Gets the current value of property by reference
     *
     * @return V
     */
    public function &getValue(): mixed
    {
        return $this->value;
    }

    /**
     * Gets the value that must be set to the field, applicable only for WRITE access type
     *
     * @return V
     */
    public function &getValueToSet(): mixed
    {
        return $this->newValue;
    }

    /**
     * @return void Covariant, as for field interceptor there is no return value
     */
    final public function proceed(): void
    {
        if (isset($this->advices[$this->current])) {
            $currentInterceptor = $this->advices[$this->current++];

            $currentInterceptor->invoke($this);
        }
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
        if ($this->level > 0) {
            $this->stackFrames[] = [$this->instance, $this->accessType, &$this->value, &$this->newValue];
        }

        try {
            ++$this->level;

            $this->current    = 0;
            $this->instance   = $instance;
            $this->accessType = $accessType;
            if ($accessType === FieldAccessType::WRITE && !isset($values[1])) {
                // Uninitialized backed typed property: no readable current value exists yet.
                /** @var V $noBackedValue */
                $noBackedValue = null;
                $this->value = $noBackedValue;
            } elseif (isset($values[0])) {
                $this->value = &$values[0];
            } else {
                /** @var V $uninitializedValue */
                $uninitializedValue = null;
                $this->value = $uninitializedValue;
            }

            if ($accessType === FieldAccessType::READ) {
                $result = &$this->value;
            } else {
                // When the current backed value is unavailable (branch above), new value is passed as index 0.
                $newValueIndex = isset($values[1]) ? 1 : 0;
                $this->newValue = &$values[$newValueIndex];
                $result = &$this->newValue;
            }
            $this->proceed();

            return $result;
        } finally {
            --$this->level;

            if ($this->level > 0 && ($stackFrame = array_pop($this->stackFrames))) {
                [$this->instance, $this->accessType, $this->value, $this->newValue] = $stackFrame;
            }
        }
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
