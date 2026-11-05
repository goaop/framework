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
     *
     * A request never weaves nor writes to the cache directory: the cache must be complete.
     * A class or an included file below the include paths that the cache has no record of
     * (added after the build, also one the weaver would leave untransformed), or a record
     * whose cached file is gone, fails the request with a WeavingException naming it, before
     * anything is woven: rebuild the cache with `cache:warmup:aop`. Files the weaver never
     * takes (outside the include paths, excluded, not `.php`) load as they are. The build
     * itself (`cache:warmup:aop`, also in place) weaves and writes as without the flag.
     */
    public const int PREBUILT_CACHE = 64;

    /**
     * Weaves a class whose parent class, interface or trait can not be located
     *
     * goaop/parser-reflection reads the ancestors of a class from their source files, found through Composer. An
     * ancestor declared by code generated at runtime, by a `class_alias()` or in a file outside the Composer
     * autoloader fails the class by default (strict). With this flag the class is reflected partially: it is matched
     * and woven with its own members and the ones of its available ancestors, members inherited from a missing
     * ancestor are not intercepted. The proxy still declares the missing parent class and interfaces.
     */
    public const int PARTIAL_REFLECTION = 256;

    /**
     * Mask of every defined feature flag
     */
    public const int ALL = self::INTERCEPT_INCLUDES
        | self::PREBUILT_CACHE
        | self::PARTIAL_REFLECTION;

    /**
     * Holder of constants, never instantiated
     *
     * @codeCoverageIgnore
     */
    private function __construct() {}
}
