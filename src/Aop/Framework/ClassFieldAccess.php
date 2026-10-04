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
     * Instance of object for accessing
     * @phpstan-var T
     */
    private object $instance;

    /**
     * Reflection of the intercepted property, created on first use: only advices ask for it
     */
    private ReflectionProperty $reflectionProperty;

    /**
     * @var class-string<T>
     */
    private readonly string $className;

    /**
     * Name of the intercepted property
     */
    private readonly string $fieldName;

    /**
     * Current value of property, bound to the stored array during {@see self::readByReference()}
     *
     * Holds null when the property is not initialized yet, see $isInitialized
     *
     * @phpstan-var V Templated type
     */
    private mixed $value = null;

    /**
     * New value to set, applicable only for WRITE access type
     *
     * @phpstan-var V Templated type
     */
    private mixed $newValue = null;

    /**
     * Whether the property had a value when the access started
     */
    private bool $isInitialized = false;

    /**
     * Access type for field access
     */
    private FieldAccessType $accessType;

    /**
     * Stack frames of the outer accesses interrupted by a nested access (an advice touching the same property)
     *
     * @var array<int, array{T, FieldAccessType, int, bool, V, V}>
     */
    private array $stackFrames = [];

    /**
     * Constructor for field access
     *
     * @param array<Interceptor> $advices List of advices for this invocation
     * @param class-string<T> $className
     */
    public function __construct(array $advices, string $className, string $fieldName)
    {
        parent::__construct($advices);
        $this->className = $className;
        $this->fieldName = $fieldName;
    }

    public function getAccessType(): FieldAccessType
    {
        return $this->accessType;
    }

    public function getField(): ReflectionProperty
    {
        return $this->reflectionProperty ??= new ReflectionProperty($this->className, $this->fieldName);
    }

    /**
     * Gets the current value of property
     *
     * @return V
     */
    public function getValue(): mixed
    {
        if (!$this->isInitialized) {
            throw new AspectException("Property {$this->fieldName} is not initialized yet");
        }

        return $this->value;
    }

    /**
     * Gets the value that must be set to the field, applicable only for WRITE access type
     *
     * @return V
     */
    public function getValueToSet(): mixed
    {
        if ($this->accessType === FieldAccessType::Read) {
            throw new AspectException("Value to set is not available for READ access type");
        }
        return $this->newValue;
    }

    /**
     * Hot path: runs on every intercepted call. The code is inlined on purpose and the frame handling is copied
     * into every joinpoint class: do not extract parts of it into methods, and do not add object allocations,
     * reflection or extra method calls here.
     */
    public function proceed(): mixed
    {
        if (isset($this->advices[$this->current])) {
            return $this->advices[$this->current++]->invoke($this);
        }
        if ($this->accessType === FieldAccessType::Write) {
            return $this->newValue;
        }
        if ($this->isInitialized) {
            return $this->value;
        }

        // Reading the property without interception fails the same way: an around advice can return a value instead
        throw new AspectException(sprintf(
            'Typed property %s::$%s must not be accessed before initialization',
            $this->getField()->class,
            $this->fieldName,
        ));
    }

    /**
     * Hot path: runs on every intercepted read. The code is inlined on purpose and the frame handling is copied
     * into every joinpoint class: do not extract parts of it into methods, and do not add object allocations,
     * reflection or extra method calls here.
     */
    public function read(object $instance, mixed $value = null): mixed
    {
        if ($this->level > 0) {
            $this->stackFrames[] = [$this->instance, $this->accessType, $this->current, $this->isInitialized, $this->value, $this->newValue];
        }
        try {
            ++$this->level;
            $this->current       = 0;
            $this->instance      = $instance;
            $this->accessType    = FieldAccessType::Read;
            $this->isInitialized = \func_num_args() > 1;
            $this->value         = $value;

            return $this->proceed();
        } finally {
            --$this->level;
            if ($this->level > 0 && ($stackFrame = array_pop($this->stackFrames)) !== null) {
                [$this->instance, $this->accessType, $this->current, $this->isInitialized, $this->value, $this->newValue] = $stackFrame;
            }
        }
    }

    /**
     * The joinpoint is bound to the stored array during the access: the result of the advices is written to it and
     * returned by reference. The binding is released afterwards, so later accesses never write to the array.
     *
     * Hot path: runs on every intercepted read of an array property. The code is inlined on purpose and the frame
     * handling is copied into every joinpoint class: do not extract parts of it into methods, and do not add object
     * allocations, reflection or extra method calls here.
     */
    public function &readByReference(object $instance, mixed &$value = null): mixed
    {
        if ($this->level > 0) {
            $this->stackFrames[] = [$this->instance, $this->accessType, $this->current, $this->isInitialized, &$this->value, $this->newValue];
        }
        try {
            ++$this->level;
            $this->current       = 0;
            $this->instance      = $instance;
            $this->accessType    = FieldAccessType::Read;
            $this->isInitialized = \func_num_args() > 1;
            $this->value         = &$value;
            $this->value         = $this->proceed();
        } finally {
            --$this->level;
            unset($this->value);
            $this->value = $value;
            if ($this->level > 0 && ($stackFrame = array_pop($this->stackFrames)) !== null) {
                [$this->instance, $this->accessType, $this->current, $this->isInitialized] = $stackFrame;
                $this->value    = &$stackFrame[4];
                $this->newValue = $stackFrame[5];
            }
        }

        return $value;
    }

    /**
     * Hot path: runs on every intercepted write. The code is inlined on purpose and the frame handling is copied
     * into every joinpoint class: do not extract parts of it into methods, and do not add object allocations,
     * reflection or extra method calls here.
     */
    public function write(object $instance, mixed $newValue, mixed $value = null): mixed
    {
        if ($this->level > 0) {
            $this->stackFrames[] = [$this->instance, $this->accessType, $this->current, $this->isInitialized, $this->value, $this->newValue];
        }
        try {
            ++$this->level;
            $this->current       = 0;
            $this->instance      = $instance;
            $this->accessType    = FieldAccessType::Write;
            $this->isInitialized = \func_num_args() > 2;
            $this->value         = $value;
            $this->newValue      = $newValue;

            return $this->proceed();
        } finally {
            --$this->level;
            if ($this->level > 0 && ($stackFrame = array_pop($this->stackFrames)) !== null) {
                [$this->instance, $this->accessType, $this->current, $this->isInitialized, $this->value, $this->newValue] = $stackFrame;
            }
        }
    }

    public function getThis(): object
    {
        return $this->instance;
    }

    public function isDynamic(): true
    {
        return true;
    }

    public function getScope(): string
    {
        return $this->instance::class;
    }

    /**
     * Returns a friendly description of current joinpoint
     */
    public function __toString(): string
    {
        return sprintf(
            '%s(%s->%s)',
            $this->accessType->value,
            $this->getScope(),
            $this->fieldName,
        );
    }
}
