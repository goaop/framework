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
 * @extends AbstractInvocation<array{list<mixed>, T|null, int}>
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
     */
    final public function proceed(): object
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
        $instance       = $this->class->newInstanceWithoutConstructor();
        $this->instance = $instance;

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
     */
    final public function __invoke(array $arguments = []): object
    {
        $this->enterFrame();
        try {
            $this->current   = 0;
            $this->arguments = $arguments;
            $this->instance  = null;

            return $this->proceed();
        } finally {
            $this->leaveFrame();
        }
    }

    protected function saveFrame(): array
    {
        return [$this->arguments, $this->instance, $this->current];
    }

    protected function restoreFrame(array $frame): void
    {
        [$this->arguments, $this->instance, $this->current] = $frame;
    }

    protected function releaseFrame(): void
    {
        $this->instance  = null;
        $this->arguments = [];
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
            $this->getScope(),
        );
    }
}
