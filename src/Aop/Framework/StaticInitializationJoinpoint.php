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

/**
 * Static initialization joinpoint is invoked after class is loaded into memory
 *
 * @template T of object = object
 * @implements ClassJoinpoint<T>
 */
class StaticInitializationJoinpoint extends AbstractJoinpoint implements ClassJoinpoint
{
    /**
     * @var class-string<T>
     */
    private string $scope;

    /**
     * Constructor for the class static initialization joinpoint
     *
     * @param array<Interceptor> $advices List of advices for this invocation
     * @param class-string<T> $className Name of the class
     */
    public function __construct(array $advices, string $className)
    {
        $this->scope = $className;
        parent::__construct($advices);
    }

    /**
     * @return void Covariant, as static initialization could not return anything
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
    /**
     * @param class-string<T>|null $scope Runtime static context, if available
     */
    final public function __invoke(?string $scope = null): void
    {
        if ($scope !== null) {
            $this->scope = $scope;
        }
        $this->current = 0;
        $this->proceed();
    }

    /**
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
        return $this->scope;
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
