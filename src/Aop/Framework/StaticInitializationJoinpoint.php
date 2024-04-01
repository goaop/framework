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
use Go\Aop\Intercept\Interceptor;
use ReflectionClass;

/**
 * Static initialization joinpoint is invoked after class is loaded into memory
 *
 * @template T of object
 */
class StaticInitializationJoinpoint extends AbstractJoinpoint implements ClassJoinpoint
{
    /**
     * @var ReflectionClass<T> Reflection of given class
     */
    private readonly ReflectionClass $reflectionClass;

    /**
     * Constructor for the class static initialization joinpoint
     *
     * @param array<Interceptor> $advices List of advices for this invocation
     * @phpstan-param class-string<T> $className Name of the class
     */
    public function __construct(array $advices, string $className)
    {
        $this->reflectionClass = new ReflectionClass($className);
        parent::__construct($advices);
    }

    /**
     * @inheritdoc
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
     * @inheritdoc
     *
     * @return null Covariance, always null for static initialization
     */
    public function getThis(): null
    {
        return null;
    }

    /**
     * @return false Covariance, always false for static method calls
     */
    public function isDynamic(): false
    {
        return false;
    }

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
