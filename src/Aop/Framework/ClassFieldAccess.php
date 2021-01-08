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
use Go\Aop\Support\AnnotatedReflectionProperty;

use function get_class;

/**
 * Represents a field access joinpoint
 */
final class ClassFieldAccess extends AbstractJoinpoint implements FieldAccess
{
    /**
     * Instance of object for accessing
     */
    protected object $instance;

    /**
     * Instance of reflection property
     */
    protected AnnotatedReflectionProperty $reflectionProperty;

    /**
     * New value to set
     *
     * @var mixed
     */
    protected $newValue;

    /**
     * Access type for field access
     */
    private int $accessType;

    /**
     * Copy of the original value of property
     *
     * @var mixed
     */
    private $value;

    /**
     * Constructor for field access
     *
     * @param array $advices List of advices for this invocation
     */
    public function __construct(array $advices, string $className, string $fieldName)
    {
        parent::__construct($advices);

        $this->reflectionProperty = $reflectionProperty = new AnnotatedReflectionProperty($className, $fieldName);
        // Give an access to protected field
        if ($reflectionProperty->isProtected()) {
            $reflectionProperty->setAccessible(true);
        }
    }

    /**
     * Returns the access type.
     */
    public function getAccessType(): int
    {
        return $this->accessType;
    }

    /**
     * Gets the field being accessed.
     *
     * Covariant return type is used
     */
    public function getField(): AnnotatedReflectionProperty
    {
        return $this->reflectionProperty;
    }

    /**
     * Gets the current value of property
     */
    public function &getValue()
    {
        $value = &$this->value;

        return $value;
    }

    /**
     * Gets the value that must be set to the field.
     */
    public function &getValueToSet()
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
     * Proceed to the next interceptor in the Chain
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
     * @param object $instance      Instance of object
     * @param int    $accessType    Type of access: READ or WRITE
     * @param mixed  $originalValue Original value of property
     * @param mixed  $newValue      New value to set
     *
     * @return mixed
     */
    final public function &__invoke(object $instance, int $accessType, &$originalValue, $newValue = NAN)
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

            if ($accessType === self::READ) {
                $result = &$this->value;
            } else {
                $result = &$this->newValue;
            }

            return $result;
        } finally {
            --$this->level;

            if ($this->level > 0) {
                [$this->instance, $this->accessType, $this->value, $this->newValue] = array_pop($this->stackFrames);
            }
        }
    }

    /**
     * Returns the object for which current joinpoint is invoked
     *
     * @return object Covariant, always instance of object, can not be null
     */
    final public function getThis(): object
    {
        return $this->instance;
    }

    /**
     * Checks if the current joinpoint is dynamic or static
     *
     * Dynamic joinpoint contains a reference to an object that can be received via getThis() method call
     *
     * @see ClassJoinpoint::getThis()
     */
    final public function isDynamic(): bool
    {
        return true;
    }

    /**
     * Returns the static scope name (class name) of this joinpoint.
     */
    final public function getScope(): string
    {
        return get_class($this->instance);
    }

    /**
     * Returns a friendly description of current joinpoint
     */
    final public function __toString(): string
    {
        return sprintf(
            '%s(%s->%s)',
            $this->accessType === self::READ ? 'get' : 'set',
            $this->getScope(),
            $this->reflectionProperty->name
        );
    }
}
