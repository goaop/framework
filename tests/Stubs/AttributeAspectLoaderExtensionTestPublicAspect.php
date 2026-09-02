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

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Before;

final class AttributeAspectLoaderExtensionTestPublicAspect implements Aspect
{
    #[Before('execution(public NonExistent\**->*(*))')]
    public function publicAdvice(MethodInvocation $invocation): void
    {
    }
}
