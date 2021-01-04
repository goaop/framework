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

use Go\Aop\Intercept\MethodInvocation;
use Go\Aop\Support\AnnotatedReflectionMethod;

use function array_merge;
use function array_pop;
use function count;

/**
 * Abstract method invocation implementation
 */
abstract class AbstractMethodInvocation extends AbstractInvocation implements MethodInvocation
{
    /**
     * Instance of object for invoking
     */
    protected ?object $instance;

    /**
     * Instance of reflection method for invocation
     */
    protected AnnotatedReflectionMethod $reflectionMethod;

    /**
     * Class name scope for static invocation
     */
    protected string $scope = '';

    /**
     * This static string variable holds the name of field to use to avoid extra "if" section in the __invoke method
     *
     * Overridden in children classes and initialized via LSB
     */
    protected static string $propertyName;

    /**
     * Constructor for method invocation
     *
     * @param array $advices List of advices for this invocation
     */
    public function __construct(array $advices, string $className, string $methodName)
    {
        parent::__construct($advices);
        $this->reflectionMethod = $method = new AnnotatedReflectionMethod($className, $methodName);

        // Give an access to call protected method
        if ($method->isProtected()) {
            $method->setAccessible(true);
        }
    }

    /**
     * Invokes current method invocation with all interceptors
     *
     * @param null|object|string $instance          Invocation instance (class name for static methods)
     * @param array              $arguments         List of arguments for method invocation
     * @param array              $variadicArguments Additional list of variadic arguments
     *
     * @return mixed Result of invocation
     */
    final public function __invoke($instance = null, array $arguments = [], array $variadicArguments = [])
    {
        if ($this->level > 0) {
            $this->stackFrames[] = [$this->arguments, $this->scope, $this->instance, $this->current];
        }

        if (count($variadicArguments) > 0) {
            $arguments = array_merge($arguments, $variadicArguments);
        }

        try {
            ++$this->level;

            $this->current   = 0;
            $this->arguments = $arguments;

            $this->{static::$propertyName} = $instance;

            return $this->proceed();
        } finally {
            --$this->level;

            if ($this->level > 0) {
                [$this->arguments, $this->scope, $this->instance, $this->current] = array_pop($this->stackFrames);
            } else {
                $this->instance  = null;
                $this->arguments = [];
            }
        }
    }

    /**
     * Gets the method being called.
     *
     * @return AnnotatedReflectionMethod Covariant, the method being called.
     */
    public function getMethod(): AnnotatedReflectionMethod
    {
        return $this->reflectionMethod;
    }

    /**
     * Returns friendly description of this joinpoint
     */
    final public function __toString(): string
    {
        return sprintf(
            'execution(%s%s%s())',
            $this->getScope(),
            $this->reflectionMethod->isStatic() ? '::' : '->',
            $this->reflectionMethod->name
        );
    }
}
