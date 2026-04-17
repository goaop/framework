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

use Go\Aop\Intercept\DynamicMethodInvocation;
use ReflectionMethod;

/**
 * Dynamic trait-alias method invocation calls instance methods via a pre-bound Closure::bind closure
 * targeting the private __aop__<method> alias created in the proxy's trait-use block.
 *
 * The closure is built once at construction time so that every invocation needs zero reflection.
 *
 * @template T of object
 * @extends AbstractMethodInvocation<T>
 * @implements DynamicMethodInvocation<T>
 */
final class DynamicTraitAliasMethodInvocation extends AbstractMethodInvocation implements DynamicMethodInvocation
{
    /**
     * For dynamic calls we store given argument as 'instance' property
     *
     * @see parent::__invoke() method to find out how this optimization works
     * @see $instance Property, which is referenced by this static property
     */
    protected static string $propertyName = 'instance';

    /**
     * @phpstan-var T Instance of object for invoking, should be protected as it's read in parent class
     * @see parent::__invoke() where this variable is accessed via {@see $propertyName} value
     */
    protected object $instance;

    /**
     * We may have either original method in the same class via trait alias or prototype method
     * from one of our parents.
     */
    private ReflectionMethod $originalMethodToCall;

    /**
     * Constructor for method invocation
     *
     * @param class-string<T> $className  Class, containing method to invoke
     */
    public function __construct(array $advices, string $className, string $methodName)
    {
        parent::__construct($advices, $className, $methodName);
        $aliasName = self::TRAIT_ALIAS_PREFIX . $methodName;
        if (method_exists($className, $aliasName)) {
            $methodToCall = new ReflectionMethod($className, $aliasName);
        } elseif ($this->reflectionMethod->hasPrototype()) {
            $methodToCall = $this->reflectionMethod->getPrototype();
        } else {
            throw new \LogicException("Cannot proceed with method invocation for {$methodName}: no trait alias and no method prototype found for {$className}");
        }
        $this->originalMethodToCall = $methodToCall;
        $this->closureToCall = static fn(object $instanceToCall, array $argumentsToCall): mixed => $methodToCall->invokeArgs($instanceToCall, $argumentsToCall);
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

        // Bypassing ($this->closureToCall)($this->instance, $this->arguments) for performance reasons
        return $this->originalMethodToCall->invokeArgs($this->instance, $this->arguments);
    }

    /**
     * @phpstan-return T Covariance, always instance of object
     */
    final public function getThis(): object
    {
        return $this->instance;
    }

    /**
     * @return true Covariance, always true for dynamic method calls
     */
    final public function isDynamic(): true
    {
        return true;
    }

    final public function getScope(): string
    {
        return $this->instance::class;
    }
}
