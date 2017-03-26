<?php
declare(strict_types = 1);
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
use Go\Aop\Intercept\Interceptor;
use ReflectionFunction;

/**
 * Function invocation implementation
 */
class ReflectionFunctionInvocation extends AbstractInvocation implements FunctionInvocation
{

    /**
     * Instance of reflection function
     *
     * @var null|ReflectionFunction
     */
    protected $reflectionFunction = null;

    /**
     * Constructor for function invocation
     *
     * @param string $functionName Function to invoke
     * @param $advices array List of advices for this invocation
     */
    public function __construct($functionName, array $advices)
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
            /** @var $currentInterceptor Interceptor */
            $currentInterceptor = $this->advices[$this->current++];

            return $currentInterceptor->invoke($this);
        }

        return $this->reflectionFunction->invokeArgs($this->arguments);
    }

    /**
     * Gets the function being called.
     */
    public function getFunction() : ReflectionFunction
    {
        return $this->reflectionFunction;
    }

    /**
     * Returns the object that holds the current joinpoint's static
     * part.
     *
     * @return object|null the object (can be null if the accessible object is
     * static).
     */
    public function getThis()
    {
        return null;
    }

    /**
     * Returns the static part of this joinpoint.
     *
     * @return object
     */
    public function getStaticPart()
    {
        return $this->reflectionFunction;
    }

    /**
     * Invokes current function invocation with all interceptors
     *
     * @param array $arguments List of arguments for function invocation
     * @param array $variadicArguments Additional list of variadic arguments
     *
     * @return mixed Result of invocation
     */
    final public function __invoke(array $arguments = [], array $variadicArguments = [])
    {
        if ($this->level > 0) {
            array_push($this->stackFrames, [$this->arguments, $this->current]);
        }

        if (!empty($variadicArguments)) {
            $arguments = array_merge($arguments, $variadicArguments);
        }

        ++$this->level;

        $this->current   = 0;
        $this->arguments = $arguments;

        $result = $this->proceed();

        --$this->level;

        if ($this->level > 0) {
            list($this->arguments, $this->current) = array_pop($this->stackFrames);
        }

        return $result;
    }

    /**
     * Returns a friendly description of current joinpoint
     *
     * @return string
     */
    final public function __toString()
    {
        return sprintf(
            "execution(%s())",
            $this->reflectionFunction->getName()
        );
    }
}
