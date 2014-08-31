<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop;

/**
 * Interface-enumeration of framework features to use in checking and configuration
 */
interface Features
{
    /**
     * Enables interception of system function.
     * By default this feature is disabled, because this option is very expensive.
     */
    const INTERCEPT_FUNCTIONS = 1;

    /**
     * Enables usage of traits and introductions as well, available since PHP5.4
     */
    const USE_TRAIT = 2;

    /**
     * Allows to use closures with binding, available since PHP5.4
     */
    const USE_CLOSURE = 4;

    /**
     * Enables usage of splat '...' operator, available since PHP5.6
     */
    const USE_SPLAT_OPERATOR = 8;

    /**
     * Do not check the cache presence and assume that cache is already prepared
     *
     * This flag is usable for read-only file systems (GAE, phar, etc)
     */
    const PREBUILT_CACHE = 16;

    /**
     * Allows to use 'static::class' in the source code of proxies, available since PHP5.5
     */
    const USE_STATIC_FOR_LSB = 32;
}
