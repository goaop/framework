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
 */
final class ClassFieldAccess extends AbstractJoinpoint implements FieldAccess
{
    /**
     * Stack frames to work with recursive calls or with cross-calls inside object
     *
     * @phpstan-var array<int, array{object, FieldAccessType, mixed, mixed}>>
     */
    private array $stackFrames = [];

    /**
     * Instance of object for accessing
     */
    private object $instance;

    /**
     * Instance of reflection property
     */
    private readonly ReflectionProperty $reflectionProperty;

    /**
     * New value to set
     */
    private mixed $newValue;

    /**
     * Access type for field access
     */
    private FieldAccessType $accessType;

    /**
     * Copy of the original value of property
     */
    private mixed $value;

    /**
     * Constructor for field access
     *
     * @param array<Interceptor> $advices List of advices for this invocation
     * @param (string&class-string) $className
     */
    public function __construct(array $advices, string $className, string $fieldName)
    {
        parent::__construct($advices);
        // We should bind our interceptor to the parent class where property is usually declared
        $parentClass = get_parent_class($className);
        if ($parentClass !== false && property_exists($parentClass, $fieldName)) {
            $className = $parentClass;
        }
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

    public function &getValue(): mixed
    {
        $value = &$this->value;

        return $value;
    }

    public function &getValueToSet(): mixed
    {
        $newValue = &$this->newValue;

        return $newValue;
    }

    /**
     * Checks scope rules for accessing property
     *
     * @internal
     */
    public function ensureScopeRule(int $stackLevel = 2): void
    {
        $property    = $this->reflectionProperty;
        $isProtected = $property->isProtected();
        $isPrivate   = $property->isPrivate();
        if ($isProtected || $isPrivate) {
            $backTrace     = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $stackLevel + 1);
            $accessor      = $backTrace[$stackLevel] ?? [];
            $propertyClass = $property->class;
            if (isset($accessor['class'])) {
                // For private and protected properties its ok to access from the same class
                if ($accessor['class'] === $propertyClass) {
                    return;
                }
                // For protected properties its ok to access from any subclass
                if ($isProtected && is_subclass_of($accessor['class'], $propertyClass)) {
                    return;
                }
            }
            throw new AspectException("Cannot access property {$propertyClass}::{$property->name}");
        }
    }

    /**
     * @inheritdoc
     *
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
     * @param mixed $originalValue Original value of property, passed by reference
     *
     * @return mixed
     */
    final public function &__invoke(object $instance, FieldAccessType $accessType, mixed &$originalValue, mixed $newValue = NAN): mixed
    {
        if ($this->level > 0) {
            $this->stackFrames[] = [$this->instance, $this->accessType, &$this->value, &$this->newValue];
        }

        try {
            ++$this->level;

            $this->current    = 0;
            $this->instance   = $instance;
            $this->accessType = $accessType;
            $this->value      = &$originalValue;
            $this->newValue   = $newValue;

            $this->proceed();

            if ($accessType === FieldAccessType::READ) {
                $result = &$this->value;
            } else {
                $result = &$this->newValue;
            }

            return $result;
        } finally {
            --$this->level;

            if ($this->level > 0 && ($stackFrame = array_pop($this->stackFrames))) {
                [$this->instance, $this->accessType, $this->value, $this->newValue] = $stackFrame;
            }
        }
    }

    /**
     * @inheritdoc
     *
     * @return object Covariant, always instance of object, can not be null
     */
    final public function getThis(): object
    {
        return $this->instance;
    }

    /**
     * @return true Covariance, always true for class properties
     */
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
