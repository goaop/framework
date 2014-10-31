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
use Go\Aop\Intercept\Joinpoint;

/**
 * "Before" interceptor of field access
 */
class FieldBeforeInterceptor extends BaseInterceptor implements AdviceBefore
{
    /**
     * Do the stuff you want to do before and after the
     * field is getted.
     *
     * @param Joinpoint|FieldAccess $field the joinpoint that corresponds to the field read
     *
     * @return mixed the result of the field read/write {@link Joinpoint::proceed()}
     */
    public function invoke(Joinpoint $field)
    {
        $adviceMethod = $this->adviceMethod;
        $adviceMethod($field);

        return $field->proceed();
    }

}
