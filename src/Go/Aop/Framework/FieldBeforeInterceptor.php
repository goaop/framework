<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Framework;

use Go\Aop\AdviceBefore;
use Go\Aop\Intercept\FieldAccess;
use Go\Aop\Intercept\FieldInterceptor;


/**
 * @package go
 */
class FieldBeforeInterceptor extends BaseInterceptor implements FieldInterceptor, AdviceBefore
{
    /**
     * Do the stuff you want to do before and after the
     * field is getted.
     *
     * <p>Polite implementations would certainly like to call
     * {@link Joinpoint::proceed()}.
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
     * <p>Polite implementations would certainly like to implement
     * {@link Joinpoint::proceed()}.
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
