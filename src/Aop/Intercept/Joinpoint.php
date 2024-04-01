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

use Stringable;

/**
 * This interface represents a generic runtime joinpoint (in the AOP terminology).
 *
 * A runtime joinpoint is an event that occurs on a static joinpoint (i.e. a location in a the program).
 * For instance, an invocation is the runtime joinpoint on a method (static joinpoint).
 *
 * Joinpoint extends system's {@see Stringable} interface for better representation during debugging.
 *
 * As Joinpoint represents different places in the code, return type for the {@see self::proceed()} can not be
 * specified in the current interface. Instead, all children classes should use covariant return types
 * to narrow return type of this method.
 *
 * @see https://www.php.net/manual/en/language.oop5.variance.php
 *
 * @see Interceptor
 * @api
 */
interface Joinpoint extends Stringable
{
    /**
     * Proceeds to the next interceptor in the chain.
     *
     * @return mixed Returns covariant return types in implementations: void, object, etc.
     *
     * @api
     */
    public function proceed();
}
