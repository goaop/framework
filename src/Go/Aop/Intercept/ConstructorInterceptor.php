<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Intercept;

/**
 * Intercepts the construction of a new object.
 *
 * <p>The user should implement the {@link construct(ConstructorInvocation)}
 * method to modify the original behavior. E.g. the following class implements a singleton
 * interceptor (allows only one unique instance for the intercepted
 * class):
 *
 * <pre class=code>
 * class DebuggingInterceptor implements ConstructorInterceptor {
 *   protected $instance=null;
 *
 *   public function construct(ConstructorInvocation $i) {
 *     if($this->instance==null) {
 *       return $this->instance=$i->proceed();
 *     } else {
 *       throw new \Exception("singleton does not allow multiple instance");
 *     }
 *   }
 * }
 * </pre> */

interface ConstructorInterceptor extends Interceptor
{

    /**
     * Implement this method to perform extra treatments before and
     * after the construction of a new object. Polite implementations
     * would certainly like to invoke {@link Joinpoint::proceed()}.
     *
     * @param ConstructorInvocation $invocation the construction joinpoint
     * @return mixed the newly created object, which is also the result of
     * the call to {@link Joinpoint::proceed()}, might be replaced by
     * the interceptor.
     */
    public function construct(ConstructorInvocation $invocation);
}
