<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Core;

use Closure;
use Error;
use ReflectionClass;
use ReflectionException;

/**
 * Creates native lazy proxies ({@see ReflectionClass::newLazyProxy()}) through a single
 * probe-based compatibility path shared by the container and generated interceptor code.
 *
 * Instead of re-deriving in userland which classes PHP can make lazy (internal classes
 * and their non-stdClass subclasses, abstract classes, enums - a set that shifts
 * between engine versions), the engine itself is asked: an incompatible class makes
 * newLazyProxy() throw. The one silent case - classes without instance properties, whose proxies come
 * back already initialized so the initializer (and with it the service factory) would
 * never run - is detected with a single isUninitializedLazyObject() call on the created
 * proxy. In the common compatible case the whole check costs one engine-level boolean.
 */
final class NativeLazyProxy
{
    /**
     * @var array<class-string, true> Classes PHP refused to make lazy, so long-running
     *      workers skip the throwing attempt on repeated registrations
     */
    private static array $unsupported = [];

    /**
     * Creates a lazy proxy of the class, or returns null when PHP cannot make the class
     * lazy - the caller is expected to fall back to eager construction
     *
     * @template T of object
     *
     * @param class-string<T> $className
     * @param Closure(T): T   $initializer Factory of the real instance, invoked on first
     *        actual interaction with the proxy
     *
     * @return T|null
     */
    public static function tryCreate(string $className, Closure $initializer): ?object
    {
        if (isset(self::$unsupported[$className])) {
            return null;
        }
        $reflection = new ReflectionClass($className);
        try {
            $proxy = $reflection->newLazyProxy($initializer);
        } catch (ReflectionException|Error) {
            self::$unsupported[$className] = true;

            return null;
        }
        // PHP creates lazy objects of classes without instance properties as already
        // initialized, so the initializer would never run for them
        if (!$reflection->isUninitializedLazyObject($proxy)) {
            self::$unsupported[$className] = true;

            return null;
        }

        return $proxy;
    }

    /**
     * Creates a lazy proxy of a class known to be proxy-compatible by construction
     * (framework-owned classes), skipping the probe and the memo
     *
     * @template T of object
     *
     * @param class-string<T> $className
     * @param Closure(T): T   $initializer
     *
     * @return T
     */
    public static function create(string $className, Closure $initializer): object
    {
        return new ReflectionClass($className)->newLazyProxy($initializer);
    }
}
