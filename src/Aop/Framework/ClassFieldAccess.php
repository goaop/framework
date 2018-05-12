<?php
declare(strict_types = 1);
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
use ReflectionProperty;

/**
 * Represents a field access joinpoint
 */
class ClassFieldAccess extends AbstractJoinpoint implements FieldAccess
{

    /**
     * Instance of object for accessing
     *
     * @var object
     */
    protected $instance;

    /**
     * Instance of reflection property
     *
     * @var ReflectionProperty
     */
    protected $reflectionProperty;

    /**
     * New value to set
     *
     * @var mixed
     */
    protected $newValue;

    /**
     * Access type for field access
     *
     * @var integer
     */
    private $accessType;

    /**
     * Copy of the original value of property
     *
     * @var mixed
     */
    private $value;

    /**
     * Constructor for field access
     *
     * @param $advices array List of advices for this invocation
     */
    public function __construct(string $className, string $fieldName, array $advices)
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
     * @return AnnotatedReflectionProperty the property which is being accessed.
     */
    public function getField(): ReflectionProperty
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
     */
    public function ensureScopeRule(int $stackLevel = 2): void
    {
        $property = $this->reflectionProperty;

        if ($property->isProtected()) {
            $backTrace     = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $stackLevel+1);
            $accessor      = $backTrace[$stackLevel] ?? [];
            $propertyClass = $property->class;
            if (isset($accessor['class'])) {
                if ($accessor['class'] === $propertyClass || is_subclass_of($accessor['class'], $propertyClass)) {
                    return;
                }
            }
            throw new AspectException("Cannot access protected property {$propertyClass}::{$property->name}");
        }
    }

    /**
     * Proceed to the next interceptor in the Chain
     *
     * @return void For field interceptor there is no return values
     */
    final public function proceed()
    {
        if (isset($this->advices[$this->current])) {
            $currentInterceptor = $this->advices[$this->current++];

            $currentInterceptor->invoke($this);
        }
    }

    /**
     * Invokes current field access with all interceptors
     *
     * @param object $instance Instance of object
     * @param integer $accessType Type of access: READ or WRITE
     * @param mixed $originalValue Original value of property
     * @param mixed $newValue New value to set
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
     * Returns the object that holds the current joinpoint's static
     * part.
     *
     * @return object|null the object (can be null if the accessible object is
     * static).
     */
    final public function getThis()
    {
        return $this->instance;
    }

    /**
     * Returns the static part of this joinpoint.
     *
     * @return object
     */
    final public function getStaticPart()
    {
        return $this->getField();
    }

    /**
     * Returns a friendly description of current joinpoint
     */
    final public function __toString(): string
    {
        return sprintf(
            '%s(%s%s%s)',
            $this->accessType === self::READ ? 'get' : 'set',
            is_object($this->instance) ? get_class($this->instance) : $this->instance,
            $this->reflectionProperty->isStatic() ? '::' : '->',
            $this->reflectionProperty->name
        );
    }
}
