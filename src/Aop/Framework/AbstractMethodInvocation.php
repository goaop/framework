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

use Closure;
use Go\Aop\Intercept\Interceptor;
use Go\Aop\Intercept\MethodInvocation;
use ReflectionMethod;

/**
 * Abstract method invocation implementation
 *
 * @template T of object Declares the instance type of the method invocation.
 * @template V Declares the generic return type of the method invocation.
 * @implements MethodInvocation<T, V>
 */
abstract class AbstractMethodInvocation extends AbstractInvocation implements MethodInvocation
{
    /**
     * Suffix used for trait method aliases that back the original method body.
     * The proxy class aliases each intercepted method as `private <method>OriginalAlias` in the trait-use block.
     */
    public const string TRAIT_ALIAS_SUFFIX = 'OriginalAlias';

    /**
     * Reflection of the intercepted method, created on first use: only advices ask for it
     */
    private ReflectionMethod $reflectionMethod;

    /**
     * @var class-string<T>
     */
    private readonly string $className;

    /**
     * @var non-empty-string
     */
    private readonly string $methodName;

    /**
     * First-class callable pointing to the original method.
     * May be wrapped or rebound if needed in child classes or during the {@see proceed()} call.
     *
     * @link https://www.php.net/manual/en/functions.first_class_callable_syntax.php
     */
    protected readonly Closure $closureToCall;

    /**
     * Constructor for method invocation
     *
     * @param array<Interceptor> $advices        List of advices for this invocation
     * @param class-string<T>    $className      Class, containing method to invoke
     * @param non-empty-string   $methodName     Name of the method to invoke
     * @param Closure            $closureToCall  First-class callable to the original method body.
     */
    public function __construct(array $advices, string $className, string $methodName, Closure $closureToCall)
    {
        parent::__construct($advices);
        $this->closureToCall = $closureToCall;
        $this->className     = $className;
        $this->methodName    = $methodName;
    }

    final public function getMethod(): ReflectionMethod
    {
        return $this->reflectionMethod ??= new ReflectionMethod($this->className, $this->methodName);
    }

    /**
     * Returns friendly description of this joinpoint
     */
    final public function __toString(): string
    {
        return sprintf(
            'execution(%s%s%s())',
            $this->getScope(),
            $this->getMethod()->isStatic() ? '::' : '->',
            $this->methodName,
        );
    }
}
