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

use Go\Aop\Intercept\ClassJoinpoint;
use Go\Core\AspectContainer;
use ReflectionClass;

use function strlen;

/**
 * Static initialization joinpoint is invoked after class is loaded into memory
 */
class StaticInitializationJoinpoint extends AbstractJoinpoint implements ClassJoinpoint
{

    protected ReflectionClass $reflectionClass;

    /**
     * Constructor for the class static initialization joinpoint
     *
     * @param array $advices List of advices for this invocation
     */
    public function __construct(array $advices, string $className)
    {
        $originalClass = $className;
        if (strpos($originalClass, AspectContainer::AOP_PROXIED_SUFFIX)) {
            $originalClass = substr($originalClass, 0, -strlen(AspectContainer::AOP_PROXIED_SUFFIX));
        }
        $this->reflectionClass = new ReflectionClass($originalClass);
        parent::__construct($advices);
    }

    /**
     * Proceeds to the next interceptor in the chain.
     *
     * @return void Covariant, as static initializtion could not return anything
     */
    public function proceed(): void
    {
        if (isset($this->advices[$this->current])) {
            $currentInterceptor = $this->advices[$this->current++];
            $currentInterceptor->invoke($this);
        }
    }

    /**
     * Invokes current joinpoint with all interceptors
     */
    final public function __invoke(): void
    {
        $this->current = 0;
        $this->proceed();
    }

    /**
     * Returns the object for which current joinpoint is invoked
     *
     * @return object|null Instance of object or null for static call/unavailable context
     */
    public function getThis(): ?object
    {
        return null;
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
        return false;
    }

    /**
     * Returns the static scope name (class name) of this joinpoint.
     */
    public function getScope(): string
    {
        return $this->reflectionClass->getName();
    }

    /**
     * Returns a friendly description of current joinpoint
     */
    final public function __toString(): string
    {
        return sprintf(
            'staticinitialization(%s)',
            $this->getScope()
        );
    }
}
