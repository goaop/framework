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

use Go\Aop\AdviceAfter;
use Go\Aop\Intercept\FieldAccess;
use Go\Aop\Intercept\FieldInterceptor;
use Go\Aop\Intercept\Joinpoint;

/**
 * "After" interceptor of field access
 */
class FieldAfterInterceptor extends BaseInterceptor implements FieldInterceptor, AdviceAfter
{
    /**
     * Do the stuff you want to do before and after the
     * field is getted.
     *
     * @param FieldAccess $field the joinpoint that corresponds to the field read
     *
     * @return mixed the result of the field read/write {@link Joinpoint::proceed()}
     */
    public function invoke(Joinpoint $field)
    {
        $value = $field->proceed();

        $adviceMethod = $this->adviceMethod;
        $adviceMethod($field);

        return $value;
    }
}
