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

use Closure;
use Go\Aop\Intercept\FunctionInvocation;
use Go\Aop\Intercept\Interceptor;
use ReflectionException;
use ReflectionFunction;

/**
 * Function invocation implementation
 *
 * Uses a first-class callable (3rd constructor argument, required) to call the original
 * function directly in {@see proceed()} without any reflection overhead.
 *
 * The callable should be a fully-qualified global function reference, e.g. `\file_get_contents(...)`,
 * with a leading backslash to avoid calling the namespace-scoped proxy recursively.  Without the
 * backslash, PHP would resolve the function name relative to the namespace of the generated proxy
 * file, which would call the proxy itself and cause infinite recursion.
 *
 * @template V = mixed Declares the generic return type of the result.
 * @extends AbstractInvocation<array{list<mixed>, int}>
 * @implements FunctionInvocation<V>
 */
final class ReflectionFunctionInvocation extends AbstractInvocation implements FunctionInvocation
{
    /**
     * Instance of reflection function
     */
    private readonly ReflectionFunction $reflectionFunction;

    /**
     * First-class callable to the original global function.
     */
    private readonly Closure $closureToCall;

    /**
     * Constructor for function invocation
     *
     * @param array<Interceptor> $advices       List of advices for this invocation
     * @param Closure            $closureToCall First-class callable to the original global function
     *                                          (e.g. `\file_get_contents(...)`).
     *
     * @throws ReflectionException
     */
    public function __construct(array $advices, string $functionName, Closure $closureToCall)
    {
        parent::__construct($advices);
        $this->reflectionFunction = new ReflectionFunction($functionName);
        $this->closureToCall      = $closureToCall;
    }

    /**
     * @return V Covariant, always mixed
     */
    public function proceed(): mixed
    {
        if (isset($this->advices[$this->current])) {
            $currentInterceptor = $this->advices[$this->current++];

            return $currentInterceptor->invoke($this);
        }

        return ($this->closureToCall)(...$this->arguments);
    }

    public function getFunction(): ReflectionFunction
    {
        return $this->reflectionFunction;
    }

    /**
     * Invokes current function invocation with all interceptors
     *
     * @param list<mixed> $arguments         List of arguments for function invocation
     * @param list<mixed> $variadicArguments Additional list of variadic arguments
     *
     * @return V Templated return type (mixed by default)
     */
    final public function __invoke(array $arguments = [], array $variadicArguments = []): mixed
    {
        if (!empty($variadicArguments)) {
            $arguments = [...$arguments, ...$variadicArguments];
        }

        $this->enterFrame();
        try {
            $this->current   = 0;
            $this->arguments = $arguments;

            return $this->proceed();
        } finally {
            $this->leaveFrame();
        }
    }

    protected function saveFrame(): array
    {
        return [$this->arguments, $this->current];
    }

    protected function restoreFrame(array $frame): void
    {
        [$this->arguments, $this->current] = $frame;
    }

    /**
     * The arguments of the last call stay readable after it returns
     */
    protected function releaseFrame(): void {}

    /**
     * Returns a friendly description of current joinpoint
     */
    final public function __toString(): string
    {
        return sprintf(
            'execution(%s())',
            $this->reflectionFunction->getName(),
        );
    }
}
