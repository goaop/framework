<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Intercept;

/**
 * Intercepts field access on a target object.
 *
 * <p>The user should implement the {@link set(FieldAccess)} and
 * {@link get(FieldAccess)} methods to modify the original
 * behavior. E.g. the following class implements a tracing interceptor
 * (traces the accesses to the intercepted field(s)):
 *
 * <pre class=code>
 * class TracingInterceptor implements FieldInterceptor {
 *
 *   public function set(FieldAccess $fa) {
 *     print("field ".$fa->getField()." is set with value ".
 *                        $fa->getValueToSet());
 *     $ret=$fa->proceed();
 *     print("field ".$fa->getField()." was set to value ".$ret);
 *     return $ret;
 *   }
 *
 *   public function get(FieldAccess $fa) {
 *     print("field ".$fa->getField()." is about to be read");
 *     $ret=$fa->proceed();
 *     print("field ".$fa->getField()." was read; value is ".$ret);
 *     return $ret;
 *   }
 * }
 * </pre>
 */
interface FieldInterceptor extends Interceptor
{

    /**
     * Do the stuff you want to do before and after the
     * field is getted.
     *
     * <p>Polite implementations would certainly like to call
     * {@link Joinpoint::proceed()}.
     *
     * @param FieldAccess $fieldRead the joinpoint that corresponds to the field read
     *
     * @return mixed the result of the field read {@link Joinpoint::proceed()}, might be intercepted by the
     * interceptor.
     */
    public function get(FieldAccess $fieldRead);

    /**
     * Do the stuff you want to do before and after the
     * field is setted.
     *
     * <p>Polite implementations would certainly like to implement
     * {@link Joinpoint::proceed()}.
     *
     * @param FieldAccess $fieldWrite the joinpoint that corresponds to the field write
     *
     * @return mixed the result of the field set {@link Joinpoint::proceed()}, might be intercepted by the
     * interceptor.
     */
    public function set(FieldAccess $fieldWrite);
}