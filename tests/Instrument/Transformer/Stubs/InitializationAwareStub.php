<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer\Stubs;

use Go\Aop\InitializationAware;

/**
 * Stand-in for a woven proxy that exposes the initialization interceptor entry point.
 *
 * ConstructorExecutionTransformer must delegate instantiation to __aop__initialization()
 * for such classes instead of building its own ReflectionConstructorInvocation.
 *
 * @implements InitializationAware<self>
 */
class InitializationAwareStub implements InitializationAware
{
    /**
     * Arguments the last __aop__initialization() call received
     *
     * @var list<mixed>
     */
    public array $receivedArguments = [];

    /**
     * @param list<mixed> $arguments
     */
    public static function __aop__initialization(array $arguments = []): object
    {
        $instance = new self();
        $instance->receivedArguments = $arguments;

        return $instance;
    }
}
