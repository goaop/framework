<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Go\Aop\AdviceBefore;
use Go\Aop\Intercept\FieldAccess;
use Go\Aop\Intercept\FieldInterceptor;

/**
 * "Before" interceptor of field access
 */
class FieldBeforeInterceptor extends BaseInterceptor implements FieldInterceptor, AdviceBefore
{
    /**
     * Do the stuff you want to do before and after the
     * field is getted.
     *
     * @param FieldAccess $fieldRead the joinpoint that corresponds to the field read
     * @return mixed the result of the field read {@link Joinpoint::proceed()}, might be intercepted by the
     * interceptor.
     */
    public function get(FieldAccess $fieldRead)
    {
        $adviceMethod = $this->adviceMethod;
        $adviceMethod($fieldRead);

        return $fieldRead->proceed();
    }

    /**
     * Do the stuff you want to do before and after the
     * field is setted.
     *
     * @param FieldAccess $fieldWrite the joinpoint that corresponds to the field write
     * @return mixed the result of the field set {@link Joinpoint::proceed()}, might be intercepted by the
     * interceptor.
     */
    public function set(FieldAccess $fieldWrite)
    {
        $adviceMethod = $this->adviceMethod;
        $adviceMethod($fieldWrite);

        return $fieldWrite->proceed();
    }
}
