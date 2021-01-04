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

use Go\Aop\Intercept\Invocation;

/**
 * Abstract class for all invocations joinpoints
 */
abstract class AbstractInvocation extends AbstractJoinpoint implements Invocation
{
    /**
     * Arguments for invocation
     */
    protected array $arguments = [];

    /**
     * Gets arguments for current invocation
     *
     * @api
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * Sets arguments for current invocation
     *
     * @api
     */
    public function setArguments(array $arguments): void
    {
        $this->arguments = $arguments;
    }
}
