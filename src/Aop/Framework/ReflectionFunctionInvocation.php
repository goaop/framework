<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Go\Aop\Intercept\FunctionInvocation;
use ReflectionException;
use ReflectionFunction;

use function array_merge;
use function array_pop;

/**
 * Function invocation implementation
 */
class ReflectionFunctionInvocation extends AbstractInvocation implements FunctionInvocation
{
    /**
     * Instance of reflection function
     */
    protected ReflectionFunction $reflectionFunction;

    /**
     * Constructor for function invocation
     *
     * @param array $advices List of advices for this invocation
     *
     * @throws ReflectionException
     */
    public function __construct(array $advices, string $functionName)
    {
        parent::__construct($advices);
        $this->reflectionFunction = new ReflectionFunction($functionName);
    }

    /**
     * Invokes original function and return result from it
     *
     * @return mixed
     */
    public function proceed()
    {
        if (isset($this->advices[$this->current])) {
            $currentInterceptor = $this->advices[$this->current++];

            return $currentInterceptor->invoke($this);
        }

        return $this->reflectionFunction->invokeArgs($this->arguments);
    }

    /**
     * Gets the function being called.
     */
    public function getFunction(): ReflectionFunction
    {
        return $this->reflectionFunction;
    }

    /**
     * Invokes current function invocation with all interceptors
     *
     * @param array $arguments         List of arguments for function invocation
     * @param array $variadicArguments Additional list of variadic arguments
     *
     * @return mixed Result of invocation
     */
    final public function __invoke(array $arguments = [], array $variadicArguments = [])
    {
        if ($this->level > 0) {
            $this->stackFrames[] = [$this->arguments, $this->current];
        }

        if (!empty($variadicArguments)) {
            $arguments = array_merge($arguments, $variadicArguments);
        }

        try {
            ++$this->level;

            $this->current   = 0;
            $this->arguments = $arguments;

            $result = $this->proceed();
        } finally {
            --$this->level;

            if ($this->level > 0) {
                [$this->arguments, $this->current] = array_pop($this->stackFrames);
            }
        }

        return $result;
    }

    /**
     * Returns a friendly description of current joinpoint
     */
    final public function __toString(): string
    {
        return sprintf(
            'execution(%s())',
            $this->reflectionFunction->getName()
        );
    }
}
