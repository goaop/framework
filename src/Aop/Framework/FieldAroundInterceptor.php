<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Go\Aop\AdviceAround;
use Go\Aop\Intercept\FieldAccess;
use Go\Aop\Intercept\FieldInterceptor;

/**
 * "Around" interceptor of field access
 */
class FieldAroundInterceptor extends BaseInterceptor implements FieldInterceptor, AdviceAround
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

        return $adviceMethod($fieldRead);
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

        return $adviceMethod($fieldWrite);
    }
}
