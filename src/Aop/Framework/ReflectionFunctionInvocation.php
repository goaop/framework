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
use Go\Aop\Intercept\Interceptor;
use ReflectionException;
use ReflectionFunction;
use function array_pop;

/**
 * Function invocation implementation
 */
final class ReflectionFunctionInvocation extends AbstractInvocation implements FunctionInvocation
{
    /**
     * Stack frames to work with recursive calls or with cross-calls inside object
     *
     * @phpstan-var array<int, array{array<mixed>, int}>
     */
    private array $stackFrames = [];

    /**
     * Instance of reflection function
     */
    private readonly ReflectionFunction $reflectionFunction;

    /**
     * Constructor for function invocation
     *
     * @param array<Interceptor> $advices List of advices for this invocation
     *
     * @throws ReflectionException
     */
    public function __construct(array $advices, string $functionName)
    {
        parent::__construct($advices);
        $this->reflectionFunction = new ReflectionFunction($functionName);
    }

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

        return $this->reflectionFunction->invokeArgs($this->arguments);
    }

    public function getFunction(): ReflectionFunction
    {
        return $this->reflectionFunction;
    }

    /**
     * Invokes current function invocation with all interceptors
     *
     * @param array<mixed> $arguments         List of arguments for function invocation
     * @param array<mixed> $variadicArguments Additional list of variadic arguments
     */
    final public function __invoke(array $arguments = [], array $variadicArguments = []): mixed
    {
        if ($this->level > 0) {
            $this->stackFrames[] = [$this->arguments, $this->current];
        }

        if (!empty($variadicArguments)) {
            $arguments = [...$arguments, ...$variadicArguments];
        }

        try {
            ++$this->level;

            $this->current   = 0;
            $this->arguments = $arguments;

            $result = $this->proceed();
        } finally {
            --$this->level;

            if ($this->level > 0 && ($stackFrame = array_pop($this->stackFrames))) {
                [$this->arguments, $this->current] = $stackFrame;
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
