<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Closure;
use Go\Aop\AspectException;

/**
 * Static closure method invocation is responsible to call static methods via closure
 */
final class StaticClosureMethodInvocation extends AbstractMethodInvocation
{
    /**
     * For static calls we store given argument as 'scope' property
     *
     * @see parent::__invoke() method to find out how this optimization works
     * @see $scope Property, which is referenced by this static property
     */
    protected static string $propertyName = 'scope';

    /**
     * @var (string&class-string) Class name scope for static invocation
     */
    protected string $scope;

    /**
     * Closure to use
     */
    protected ?Closure $closureToCall;

    /**
     * @var null|(string&class-string) Previous scope of invocation or null for first call
     */
    protected ?string $previousScope = null;

    /**
     * @inheritdoc
     * @return mixed Covariant, always mixed
     */
    public function proceed(): mixed
    {
        if (isset($this->advices[$this->current])) {
            $currentInterceptor = $this->advices[$this->current++];

            return $currentInterceptor->invoke($this);
        }

        // Rebind the closure if scope (class name) was changed since last time
        if ($this->previousScope !== $this->scope) {
            if (!isset($this->closureToCall)) {
                $this->closureToCall = self::getStaticInvoker(
                    $this->reflectionMethod->class,
                    $this->reflectionMethod->name
                );
            }
            $this->closureToCall = $this->closureToCall->bindTo(null, $this->scope);
            $this->previousScope = $this->scope;
        }

        return ($this->closureToCall)?->__invoke($this->arguments);
    }

    /**
     * Returns static method invoker for the concrete method in the class
     */
    protected static function getStaticInvoker(string $className, string $methodName): Closure
    {
        $staticCallback = [$className, $methodName];
        // We can not check callable fully because of protected static methods, as we will be inside LSB call later
        if (!is_callable($staticCallback, true)) {
            throw new AspectException("Invalid static callback given {$className}::{$methodName}");
        }

        return fn(array $arguments): mixed => forward_static_call_array($staticCallback, $arguments);
    }

    /**
     * @return false Covariance, always false for static initialization
     */
    final public function isDynamic(): false
    {
        return false;
    }

    /**
     * @inheritdoc
     *
     * @return null Covariance, always null for static invocations
     */
    final public function getThis(): null
    {
        return null;
    }

    final public function getScope(): string
    {
        // $this->scope contains the current class scope that was received via static::class
        return $this->scope;
    }
}
