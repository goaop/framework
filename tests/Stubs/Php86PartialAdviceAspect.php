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

// PHP 8.6-only syntax (partial function application). This file must only be loaded from tests gated with
// #[RequiresPhp('>= 8.6.0-dev')].

namespace Go\Stubs;

use Closure;
use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;

/**
 * Aspect handing out a partial application of its own method as an advice (issue #790)
 *
 * The partial application is scoped to the aspect class wherever it is created, but it is an anonymous closure with
 * a bound argument, not the advice method itself.
 */
final class Php86PartialAdviceAspect implements Aspect
{
    public function advice(MethodInvocation $invocation, int $ttl = 60): mixed
    {
        return $invocation->proceed();
    }

    public function partialAdvice(): Closure
    {
        return $this->advice(?, 300);
    }
}
