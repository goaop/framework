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
use Go\Core\AspectContainer;
use ReflectionClass;
use ReflectionMethod;

/**
 * Reflection constructor invocation implementation
 */
class ReflectionConstructorInvocation extends AbstractInvocation implements ConstructorInvocation
{
    /**
     * Reflection class
     */
    protected ReflectionClass $class;

    /**
     * Instance of created class, can be used for Around or After types of advices.
     */
    protected ?object $instance = null;

    /**
     * Instance of reflection constructor for class (if present)
     */
    private ?ReflectionMethod $constructor;

    /**
     * Constructor for constructor invocation :)
     *
     * @param array $advices List of advices for this invocation
     */
    public function __construct(array $advices, string $className)
    {
        $originalClass = $className;
        if (strpos($originalClass, AspectContainer::AOP_PROXIED_SUFFIX) !== false) {
            $originalClass = substr($originalClass, 0, -strlen(AspectContainer::AOP_PROXIED_SUFFIX));
        }

        $this->class       = new ReflectionClass($originalClass);
        $this->constructor = $constructor = $this->class->getConstructor();

        // Give an access to call protected/private constructors
        if ($constructor !== null && !$constructor->isPublic()) {
            $constructor->setAccessible(true);
        }

        parent::__construct($advices);
    }

    /**
     * Proceed to the next interceptor in the Chain
     *
     * Typically this method is called inside previous closure, as instance of Joinpoint is passed to callback
     * Do not call this method directly, only inside callback closures.
     *
     * @return object Covariant, always new object.
     */
    final public function proceed(): object
    {
        if (isset($this->advices[$this->current])) {
            $currentInterceptor = $this->advices[$this->current];
            $this->current++;

            return $currentInterceptor->invoke($this);
        }

        $this->instance = $this->class->newInstanceWithoutConstructor();
        $constructor    = $this->getConstructor();
        if ($constructor !== null) {
            $constructor->invoke($this->instance, ...$this->arguments);
        }

        return $this->instance;
    }

    /**
     * Gets the constructor being called or null if it is absent.
     */
    public function getConstructor(): ?ReflectionMethod
    {
        return $this->constructor;
    }

    /**
     * Returns the object for which current joinpoint is invoked
     *
     * @return object|null Instance of object or null if object hasn't been created yet (Before)
     */
    public function getThis(): ?object
    {
        return $this->instance;
    }

    /**
     * Invokes current constructor invocation with all interceptors
     */
    final public function __invoke(array $arguments = []): object
    {
        $this->current   = 0;
        $this->arguments = $arguments;

        return $this->proceed();
    }

    /**
     * Checks if the current joinpoint is dynamic or static
     *
     * Dynamic joinpoint contains a reference to an object that can be received via getThis() method call
     *
     * @see ClassJoinpoint::getThis()
     */
    public function isDynamic(): bool
    {
        return true;
    }

    /**
     * Returns the static scope name (class name) of this joinpoint.
     */
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
