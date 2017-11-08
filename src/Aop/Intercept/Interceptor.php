<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Intercept;

use Go\Aop\Advice;

/**
 * This interface represents a generic interceptor.
 *
 * A generic interceptor can intercept runtime events that occur
 * within a base program. Those events are materialized by (reified
 * in) joinpoints. Runtime joinpoints can be invocations, field
 * access, exceptions...
 *
 * This interface is not used directly. Use the the sub-interfaces
 * to intercept specific events. For instance, the following class
 * implements some specific interceptors in order to implement a
 * debugger:
 *
 * <pre class=code>
 * class DebuggingInterceptor implements Interceptor
 * {
 *     public function invoke(Joinpoint $i)
 *     {
 *         $this->debug($i->getStaticPart(), $i->getThis(), $i->getArgs());
 *
 *         return $i->proceed();
 *     }
 *
 *     protected function debug($accessibleObject, $object, $value)
 *     {
 *         ...
 *     }
 * }
 * </pre>
 *
 * @see Joinpoint
 * @api
 */
interface Interceptor extends Advice
{
    /**
     * Implement this method to perform extra actions before and after the invocation of joinpoint.
     *
     * @param Joinpoint $joinpoint Current joinpoint
     * @api
     *
     * @return mixed the result of the call
     */
    public function invoke(Joinpoint $joinpoint);
}
