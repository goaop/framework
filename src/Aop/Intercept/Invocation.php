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

namespace Go\Aop\Intercept;

/**
 * This interface represents an invocation in the program
 *
 * An invocation is a callable joinpoint and can be intercepted by an interceptor. All invocation
 * instances provide API for accessing/modification of invocation arguments.
 *
 * @api
 */
interface Invocation extends Joinpoint
{
    /**
     * Get the arguments of invocation as an array.
     *
     * @return array<mixed>
     * @api
     */
    public function getArguments(): array;

    /**
     * Sets the arguments for current invocation
     *
     * @param array<mixed> $arguments New list of arguments
     * @api
     */
    public function setArguments(array $arguments): void;
}
