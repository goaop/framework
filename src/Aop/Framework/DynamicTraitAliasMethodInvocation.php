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

/**
 * Dynamic trait-alias method invocation calls instance methods via a pre-bound Closure::bind closure
 * targeting the private __aop__<method> alias created in the proxy's trait-use block.
 *
 * The closure is built once at construction time so that every invocation needs zero reflection.
 */
final class DynamicTraitAliasMethodInvocation extends AbstractMethodInvocation
{
    /**
     * For dynamic calls we store given argument as 'instance' property
     *
     * @see parent::__invoke() method to find out how this optimization works
     * @see $instance Property, which is referenced by this static property
     */
    protected static string $propertyName = 'instance';

    /**
     * @var object Instance of object for invoking, should be protected as it's read in parent class
     * @see parent::__invoke() where this variable is accessed via {@see $propertyName} value
     */
    protected object $instance;

    /**
     * Pre-bound closure that calls the private __aop__<method> alias on any instance of the proxy class.
     * Created once in the constructor via Closure::bind bound to the proxy class scope.
     */
    private readonly Closure $closureToCall;

    public function __construct(array $advices, string $className, string $methodName)
    {
        parent::__construct($advices, $className, $methodName);
        $aliasName           = self::TRAIT_ALIAS_PREFIX . $methodName;
        $this->closureToCall = Closure::bind(
            static fn(object $instanceToCall, array $argumentsToCall): mixed => $instanceToCall->$aliasName(...$argumentsToCall),
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

        return ($this->closureToCall)($this->instance, $this->arguments);
    }

    /**
     * @return object Covariance, always instance of object
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
