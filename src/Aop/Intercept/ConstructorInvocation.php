<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Intercept;

use ReflectionMethod;

/**
 * Description of an invocation to a constructor, given to an
 * interceptor upon constructor-call.
 *
 * A constructor invocation is a joinpoint and can be intercepted
 * by a constructor interceptor.
 */
interface ConstructorInvocation extends Invocation
{

    /**
     * Gets the constructor being called.
     *
     * @return ReflectionMethod|null the constructor being called or null if it is absent.
     */
    public function getConstructor();
}
