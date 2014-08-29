<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Intercept;

/**
 * Intercepts the construction of a new object.
 *
 * The user should implement the construct(ConstructorInvocation)
 * method to modify the original behavior. E.g. the following class implements a singleton
 * interceptor (allows only one unique instance for the intercepted
 * class):
 *
 * <pre class=code>
 * class DebuggingInterceptor implements ConstructorInterceptor {
 *   protected $instance=null;
 *
 *   public function construct(ConstructorInvocation $i) {
 *     if ($this->instance==null) {
 *       return $this->instance=$i->proceed();
 *     } else {
 *       throw new \Exception("singleton does not allow multiple instance");
 *     }
 *   }
 * }
 * </pre>
 */
interface ConstructorInterceptor extends Interceptor
{

    /**
     * Implement this method to perform extra treatments before and
     * after the construction of a new object.
     *
     * @param ConstructorInvocation $invocation the construction joinpoint
     * @return mixed the newly created object
     */
    public function construct(ConstructorInvocation $invocation);
}
