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

namespace Go\Aop\Intercept;

use ReflectionFunction;

/**
 * Description of an invocation to a function, given to an interceptor upon function-call.
 *
 * A function invocation is a joinpoint and can be intercepted by a function interceptor.
 *
 * @api
 *`
 * @template V = mixed Declares the generic return type of the result.
 */
interface FunctionInvocation extends Invocation
{
    /**
     * Gets the function being called.
     */
    public function getFunction(): ReflectionFunction;

    /**
     * Invokes current function invocation with all interceptors
     *
     * @param list<mixed> $arguments         List of arguments for function invocation
     * @param list<mixed> $variadicArguments Additional list of variadic arguments
     *
     * @phpstan-return V Templated return type (mixed by default)
     */
    public function __invoke(array $arguments = [], array $variadicArguments = []): mixed;
}
