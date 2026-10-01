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

namespace Go\Stubs\Collision\A;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;

/**
 * Shares its short name with the aspect in the sibling namespace: generated code must
 * reference both unambiguously (see the use-collision tests of the proxy generators)
 */
final class SameNameAspect implements Aspect
{
    public function beforeMethod(MethodInvocation $invocation): void {}
}
