<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Intercept;

use ReflectionMethod;

/**
 * Description of an invocation to a constructor, given to an
 * interceptor upon constructor-call.
 *
 * <p>A constructor invocation is a joinpoint and can be intercepted
 * by a constructor interceptor.
 *
 * @see ConstructorInterceptor
 */
interface ConstructorInvocation extends Invocation
{

    /**
     * Gets the constructor being called.
     *
     * <p>This method is a friendly implementation of the
     * {@link Joinpoint::getStaticPart()} method (same result).
     *
     * @return ReflectionMethod the constructor being called. */
    public function getConstructor();
}
