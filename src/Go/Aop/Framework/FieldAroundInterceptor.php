<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Framework;

use Go\Aop\AdviceAround;
use Go\Aop\Intercept\FieldAccess;
use Go\Aop\Intercept\FieldInterceptor;


/**
 * @package go
 */
class FieldAroundInterceptor extends BaseInterceptor implements FieldInterceptor, AdviceAround
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
        return $adviceMethod($fieldRead);
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
        return $adviceMethod($fieldWrite);
    }
}
