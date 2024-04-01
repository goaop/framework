<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2019, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Intercept;

/**
 * This interface represents a class joinpoint that can have $this variable and defined scope
 *
 * @api
 */
interface ClassJoinpoint extends Joinpoint
{
    /**
     * Checks if the current joinpoint is dynamic or static
     *
     * Dynamic joinpoint contains a reference to an object that can be received via {@see getThis()} method call
     *
     * @see ClassJoinpoint::getThis()
     *
     * @api
     */
    public function isDynamic(): bool;

    /**
     * Returns the object for which current joinpoint is invoked or null for static calls
     *
     * @api
     */
    public function getThis(): ?object;

    /**
     * Returns the static scope name (class name) of this joinpoint.
     *
     * @return (string&class-string)
     *
     * @api
     */
    public function getScope(): string;
}
