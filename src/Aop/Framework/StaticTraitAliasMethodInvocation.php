<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Closure;
use Go\Aop\Intercept\StaticMethodInvocation;

/**
 * Static trait-alias method invocation calls static methods via a pre-bound Closure::bind closure
 * targeting the private __aop__<method> alias created in the proxy's trait-use block.
 *
 * The closure is built once at construction time so that every invocation needs zero reflection.
 *
 * @template T of object = object
 * @extends AbstractMethodInvocation<T>
 * @implements StaticMethodInvocation<T>
 */
final class StaticTraitAliasMethodInvocation extends AbstractMethodInvocation implements StaticMethodInvocation
{
    /**
     * For static calls we store given argument as 'scope' property
     *
     * @see parent::__invoke() method to find out how this optimization works
     * @see $scope Property, which is referenced by this static property
     */
    protected static string $propertyName = 'scope';

    /**
     * @var class-string<T> Class name scope for static invocation
     */
    protected string $scope;

    /**
     * Constructor for method invocation
     *
     * @param class-string<T> $className  Class, containing method to invoke
     */
    public function __construct(array $advices, string $className, string $methodName)
    {
        parent::__construct($advices, $className, $methodName);
        $aliasName           = self::TRAIT_ALIAS_PREFIX . $methodName;
        $this->closureToCall = Closure::bind(
            static fn(string $classToCall, array $argumentsToCall): mixed => $classToCall::$aliasName(...$argumentsToCall),
            null,
            $className
        );
    }

    /**
     * @return mixed Covariant, always mixed
     */
    public function proceed(): mixed
    {
        if (isset($this->advices[$this->current])) {
            $currentInterceptor = $this->advices[$this->current++];

            return $currentInterceptor->invoke($this);
        }

        return ($this->closureToCall)($this->scope, $this->arguments);
    }

    /**
     * @return false Covariance, always false for static method calls
     */
    final public function isDynamic(): false
    {
        return false;
    }

    /**
     * @return null Covariance, always null for static invocations
     */
    final public function getThis(): null
    {
        return null;
    }

    final public function getScope(): string
    {
        return $this->scope;
    }
}
