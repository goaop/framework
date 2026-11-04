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

namespace Go\Stubs;

use Closure;
use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;

/**
 * Aspect handing out an advice closure created inside the aspect (issue #790)
 *
 * The closure is scoped to the aspect class, but it is not one of its methods: it must be referenced as a container
 * advice, never as `The::aspect(AnonymousAdviceAspect::class)->{closure:...}(...)`.
 */
final class AnonymousAdviceAspect implements Aspect
{
    public function advice(MethodInvocation $invocation, int $ttl = 60): mixed
    {
        return $invocation->proceed();
    }

    public function closureAdvice(): Closure
    {
        return fn(MethodInvocation $invocation): mixed => $this->advice($invocation, 300);
    }
}
