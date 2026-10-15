<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop;

/**
 * Framework feature flags for the `features` kernel option, combined with `|`
 *
 * Interception of system functions and of `new` needs no flag: it is enabled by the registered
 * `execution(Namespace\function(*))` and `initialization(...)` pointcuts themselves.
 */
final class Features
{
    /**
     * Enables interception of "include"/"require" operations in legacy code
     * By default this feature is disabled, because only composer should be used
     */
    public const int INTERCEPT_INCLUDES = 4;

    /**
     * Trust the cache built at deploy time (`bin/aspect cache:warmup:aop`) unconditionally
     *
     * Skips every cache-related stat/freshness check at runtime: cache directory
     * existence/writability probes, source filemtime comparisons, tracked-resource
     * checks and advisor cache freshness. Staleness is the deployer's responsibility -
     * rebuild the cache on every deployment. Also usable for read-only file systems
     * (GAE, phar, etc).
     */
    public const int PREBUILT_CACHE = 64;

    /**
     * Mask of every defined feature flag
     */
    public const int ALL = self::INTERCEPT_INCLUDES
        | self::PREBUILT_CACHE;

    /**
     * Holder of constants, never instantiated
     *
     * @codeCoverageIgnore
     */
    private function __construct() {}
}
