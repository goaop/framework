<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace org\aopalliance\intercept;

/**
 * Description of an invocation to a constuctor, given to an
 * interceptor upon construtor-call.
 *
 * <p>A constructor invocation is a joinpoint and can be intercepted
 * by a constructor interceptor.
 *
 * @see ConstructorInterceptor */
interface ConstructorInvocation extends Invocation {

    /**
     * Gets the constructor being called.
     *
     * <p>This method is a frienly implementation of the
     * {@link Joinpoint::getStaticPart()} method (same result).
     *
     * @return \ReflectionMethod the constructor being called. */
    public function getConstructor();
}
