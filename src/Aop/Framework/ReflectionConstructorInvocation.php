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
 * @template T of object
 */
class ReflectionConstructorInvocation extends AbstractInvocation implements ConstructorInvocation
{
    /**
     * @var ReflectionClass<T> Reflection of given class
     */
    private readonly ReflectionClass $class;

    /**
     * @var null|(object&T) Instance of created class, can be used for Around or After types of advices.
     */
    private ?object $instance = null;

    /**
     * Instance of reflection constructor for class (if present)
     */
    private readonly ?ReflectionMethod $constructor;

    /**
     * Constructor for constructor invocation :)
     *
     * @param array<Interceptor>      $advices List of advices for this invocation
     * @phpstan-param class-string<T> $className Name of the class
     */
    public function __construct(array $advices, string $className)
    {
        $this->class       = new ReflectionClass($className);
        $this->constructor = $this->class->getConstructor();

        parent::__construct($advices);
    }

    /**
     * @inheritdoc
     *
     * @return (mixed|T) Covariant, always new object.
     * @throws \ReflectionException If class is internal and cannot be created without constructor
     */
    final public function proceed(): mixed
    {
        if (isset($this->advices[$this->current])) {
            $currentInterceptor = $this->advices[$this->current];
            $this->current++;

            return $currentInterceptor->invoke($this);
        }

        $this->instance = $this->class->newInstanceWithoutConstructor();

        // Null-safe invocation of constructor with constructor arguments
        $this->getConstructor()?->invoke($this->instance, ...$this->arguments);

        return $this->instance;
    }

    public function getConstructor(): ?ReflectionMethod
    {
        return $this->constructor;
    }

    /**
     * Returns the object for which current joinpoint is invoked
     *
     * @return (object&T)|null Instance of object or null if object hasn't been created yet (Before)
     */
    public function getThis(): ?object
    {
        return $this->instance;
    }

    /**
     * Invokes current constructor invocation with all interceptors
     *
     * @param array<mixed> $arguments Arguments for constructor invocation
     * @return (mixed|T) Instance of object or anything else from interceptors, eg Around type can replace object
     */
    final public function __invoke(array $arguments = []): mixed
    {
        $this->current   = 0;
        $this->arguments = $arguments;

        return $this->proceed();
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
    final public function __toString(): string
    {
        return sprintf(
            'initialization(%s)',
            $this->getScope()
        );
    }
}
