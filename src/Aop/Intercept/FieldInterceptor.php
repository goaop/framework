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
 * Intercepts field access on a target object.
 *
 * The user should implement the set(FieldAccess) and
 * get(FieldAccess) methods to modify the original
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
     * @param FieldAccess $fieldRead the joinpoint that corresponds to the field read
     *
     * @return mixed the result of the field read
     */
    public function get(FieldAccess $fieldRead);

    /**
     * Do the stuff you want to do before and after the
     * field is setted.
     *
     * @param FieldAccess $fieldWrite the joinpoint that corresponds to the field write
     *
     * @return mixed the result of the field set
     */
    public function set(FieldAccess $fieldWrite);
}
