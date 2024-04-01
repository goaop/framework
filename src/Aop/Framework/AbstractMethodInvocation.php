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

use Go\Aop\Intercept\Interceptor;
use Go\Aop\Intercept\MethodInvocation;
use ReflectionMethod;
use function array_merge;
use function array_pop;
use function count;

/**
 * Abstract method invocation implementation
 */
abstract class AbstractMethodInvocation extends AbstractInvocation implements MethodInvocation
{
    protected readonly ReflectionMethod $reflectionMethod;

    /**
     * This static string variable holds the name of field to use to avoid extra "if" section in the __invoke method
     *
     * Overridden in children classes and initialized via LSB
     */
    protected static string $propertyName;

    /**
     * Stack frames to work with recursive calls or with cross-calls inside object
     *
     * @var (array&array<int, array{array<mixed>, object|class-string, int}>)
     */
    private array $stackFrames = [];

    /**
     * Constructor for method invocation
     *
     * @param array<Interceptor>        $advices List of advices for this invocation
     * @param (string&class-string)     $className Class, containing method to invoke
     * @param (string&non-empty-string) $methodName Name of the method to invoke
     */
    public function __construct(array $advices, string $className, string $methodName)
    {
        parent::__construct($advices);
        $reflectionMethod = new ReflectionMethod($className, $methodName);

        // If we have method inside AOP proxy class, we would like to use prototype instead
        if ($reflectionMethod->hasPrototype()) {
            $reflectionMethod = $reflectionMethod->getPrototype();
        }
        $this->reflectionMethod = $reflectionMethod;
    }

    final public function __invoke(object|string $instanceOrScope, array $arguments = [], array $variadicArguments = []): mixed
    {
        if ($this->level > 0) {
            $this->stackFrames[] = [$this->arguments, $this->{static::$propertyName}, $this->current];
        }

        if (count($variadicArguments) > 0) {
            $arguments = array_merge($arguments, $variadicArguments);
        }

        try {
            ++$this->level;

            $this->current   = 0;
            $this->arguments = $arguments;

            $this->{static::$propertyName} = $instanceOrScope;

            return $this->proceed();
        } finally {
            --$this->level;

            if ($this->level > 0 && ($stackFrame = array_pop($this->stackFrames))) {
                [$this->arguments, $this->{static::$propertyName}, $this->current] = $stackFrame;
            } else {
                unset($this->{static::$propertyName});
                $this->arguments = [];
            }
        }
    }

    public function getMethod(): ReflectionMethod
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
