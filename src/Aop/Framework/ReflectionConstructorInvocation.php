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

use Go\Aop\Intercept\ConstructorInvocation;
use Go\Aop\Intercept\Interceptor;
use ReflectionClass;
use ReflectionMethod;

/**
 * Reflection constructor invocation implementation
 *
 * @template T of object = object
 * @implements ConstructorInvocation<T>
 */
final class ReflectionConstructorInvocation extends AbstractInvocation implements ConstructorInvocation
{
    /**
     * @var ReflectionClass<T> Reflection of given class
     */
    private readonly ReflectionClass $class;

    /**
     * @phpstan-var null|T Instance of created class, can be used for Around or After types of advices.
     */
    private ?object $instance = null;

    /**
     * Stack frames of the outer constructions interrupted by a nested `new` of the same class
     *
     * @var array<int, array{list<mixed>, T|null, int}>
     */
    private array $stackFrames = [];

    /**
     * Instance of reflection constructor for class (if present)
     */
    private readonly ?ReflectionMethod $constructor;

    /**
     * Constructor for constructor invocation :)
     *
     * @param array<Interceptor>    $advices List of advices for this invocation
     * @param class-string<T>       $className Name of the class
     */
    public function __construct(array $advices, string $className)
    {
        $this->class       = new ReflectionClass($className);
        $this->constructor = $this->class->getConstructor();

        parent::__construct($advices);
    }

    /**
     * @phpstan-return T
     * @throws \ReflectionException If class is internal and cannot be created without constructor
     *
     * Hot path: runs on every intercepted call. The code is inlined on purpose and the frame handling is copied
     * into every joinpoint class: do not extract parts of it into methods, and do not add object allocations,
     * reflection or extra method calls here.
     */
    public function proceed(): object
    {
        if (isset($this->advices[$this->current])) {
            $currentInterceptor = $this->advices[$this->current];
            $this->current++;
            /** @var T $result */
            $result = $currentInterceptor->invoke($this);

            return $result;
        }

        // A nested `new` of the same class inside the constructor replaces $this->instance until it returns,
        // so the created object is kept locally
        $instance = $this->instance = $this->class->newInstanceWithoutConstructor();

        // Null-safe invocation of constructor with constructor arguments
        $this->getConstructor()?->invoke($instance, ...$this->arguments);

        return $instance;
    }

    public function getConstructor(): ?ReflectionMethod
    {
        return $this->constructor;
    }

    /**
     * Returns the object for which current joinpoint is invoked
     *
     * @phpstan-return T|null Instance of object or null if object hasn't been created yet (Before)
     */
    public function getThis(): ?object
    {
        return $this->instance;
    }

    /**
     * Invokes current constructor invocation with all interceptors
     *
     * @param list<mixed> $arguments Arguments for constructor invocation
     * @phpstan-return T Instance of object
     *
     * Hot path: runs on every intercepted call. The code is inlined on purpose and the frame handling is copied
     * into every joinpoint class: do not extract parts of it into methods, and do not add object allocations,
     * reflection or extra method calls here.
     */
    public function __invoke(array $arguments = []): object
    {
        if ($this->level > 0) {
            $this->stackFrames[] = [$this->arguments, $this->instance, $this->current];
        }
        try {
            ++$this->level;
            $this->current   = 0;
            $this->arguments = $arguments;
            $this->instance  = null;

            return $this->proceed();
        } finally {
            --$this->level;
            if ($this->level > 0 && ($stackFrame = array_pop($this->stackFrames))) {
                [$this->arguments, $this->instance, $this->current] = $stackFrame;
            } else {
                // The shared invocation must not keep the created object alive
                $this->instance  = null;
                $this->arguments = [];
            }
        }
    }

    /**
     * @return true Covariance, always true for new object creation
     */
    public function isDynamic(): true
    {
        return true;
    }

    public function getScope(): string
    {
        return $this->class->getName();
    }

    /**
     * Returns a friendly description of current joinpoint
     */
    public function __toString(): string
    {
        return sprintf(
            'initialization(%s)',
            $this->getScope(),
        );
    }
}
