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
 * Interface-enumeration of framework features to use in checking and configuration
 */
interface Features
{
    /**
     * Enables interception of system function.
     * By default this feature is disabled, because this option is very expensive.
     */
    public const int INTERCEPT_FUNCTIONS = 1;

    /**
     * Enables interception of "new" operator in the source code
     * By default this feature is disabled, because it's very tricky
     */
    public const int INTERCEPT_INITIALIZATIONS = 2;

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
}
