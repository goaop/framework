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
     * @var array<mixed> Arguments for invocation, can be mutated by the {@see setArguments()} method
     */
    protected array $arguments = [];

    final public function getArguments(): array
    {
        return $this->arguments;
    }

    final public function setArguments(array $arguments): void
    {
        $this->arguments = $arguments;
    }
}
