<?php
declare(strict_types = 1);
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
use ReflectionMethod;

/**
 * Abstract method invocation implementation
 */
abstract class AbstractMethodInvocation extends AbstractInvocation implements MethodInvocation
{

    /**
     * Instance of object for invoking or class name for static call
     *
     * @var object|string
     */
    protected $instance;

    /**
     * Instance of reflection method for class
     *
     * @var ReflectionMethod
     */
    protected $reflectionMethod;

    /**
     * Name of the invocation class
     *
     * @var string
     */
    protected $className = '';

    /**
     * Constructor for method invocation
     *
     * @param string $className Class name
     * @param string $methodName Method to invoke
     * @param $advices array List of advices for this invocation
     */
    public function __construct(string $className, string $methodName, array $advices)
    {
        parent::__construct($advices);
        $this->className        = $className;
        $this->reflectionMethod = $method = new AnnotatedReflectionMethod($this->className, $methodName);

        // Give an access to call protected method
        if ($method->isProtected()) {
            $method->setAccessible(true);
        }
    }

    /**
     * Invokes current method invocation with all interceptors
     *
     * @param null|object|string $instance Invocation instance (class name for static methods)
     * @param array $arguments List of arguments for method invocation
     * @param array $variadicArguments Additional list of variadic arguments
     *
     * @return mixed Result of invocation
     */
    final public function __invoke($instance = null, array $arguments = [], array $variadicArguments = [])
    {
        if ($this->level > 0) {
            $this->stackFrames[] = [$this->arguments, $this->instance, $this->current];
        }

        if (!empty($variadicArguments)) {
            $arguments = array_merge($arguments, $variadicArguments);
        }

        try {
            ++$this->level;

            $this->current   = 0;
            $this->instance  = $instance;
            $this->arguments = $arguments;

            $result = $this->proceed();
        } finally {
            --$this->level;
        }

        if ($this->level > 0) {
            list($this->arguments, $this->instance, $this->current) = array_pop($this->stackFrames);
        }

        return $result;
    }

    /**
     * Gets the method being called.
     *
     * @return ReflectionMethod|AnnotatedReflectionMethod the method being called.
     */
    public function getMethod() : ReflectionMethod
    {
        return $this->reflectionMethod;
    }

    /**
     * Returns the object that holds the current joinpoint's static
     * part.
     *
     * @return object|string the object for dynamic call or string with name of scope
     */
    public function getThis()
    {
        return $this->instance;
    }

    /**
     * Returns the static part of this joinpoint.
     *
     * @return object
     */
    public function getStaticPart()
    {
        return $this->getMethod();
    }

    /**
     * Returns friendly description of this joinpoint
     *
     * @return string
     */
    final public function __toString()
    {
        return sprintf(
            "execution(%s%s%s())",
            is_object($this->instance) ? get_class($this->instance) : $this->instance,
            $this->reflectionMethod->isStatic() ? '::' : '->',
            $this->reflectionMethod->name
        );
    }
}
