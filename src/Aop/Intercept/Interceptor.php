<?php

declare(strict_types=1);
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
 * to intercept specific events.
 *
 * @see Joinpoint
 * @api
 */
interface Interceptor extends Advice
{
    /**
     * Performs extra actions before, after or around the invocation of joinpoint.
     *
     * @api
     */
    public function invoke(Joinpoint $joinpoint): mixed;
}
