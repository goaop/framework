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
use ReflectionProperty;

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
 *
 * @internal Used by the container for lazy services
 */
final class NativeLazyProxy
{
    /**
     * Static facade, never instantiated
     *
     * @codeCoverageIgnore
     */
    private function __construct() {}

    /**
     * @var array<class-string, true> Classes PHP refused to make lazy, so long-running
     *      workers skip the throwing attempt on repeated registrations
     */
    private static array $unsupported = [];

    /**
     * @var array<class-string, array<string, ReflectionProperty>> Properties set by {@see create()}, by class and name
     */
    private static array $rawProperties = [];

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
     * The properties given in $rawValues are set on the proxy without initializing it, so they can be read
     * without running the initializer. The initializer must give the real instance the same values: once the
     * proxy is initialized, reads go to the real instance. At least one property has to stay unset, or PHP
     * marks the proxy as initialized and the initializer never runs.
     *
     * @template T of object
     *
     * @param class-string<T>      $className
     * @param Closure(T): T        $initializer
     * @param array<string, mixed> $rawValues Values of properties, by property name
     *
     * @return T
     */
    public static function create(string $className, Closure $initializer, array $rawValues = []): object
    {
        $reflection = new ReflectionClass($className);
        $proxy      = $reflection->newLazyProxy($initializer);
        foreach ($rawValues as $propertyName => $value) {
            // Taken from the declaring class: PHP refuses to set an inherited private(set) property in the scope
            // of a child class
            $property = self::$rawProperties[$className][$propertyName] ??= new ReflectionProperty(
                $reflection->getProperty($propertyName)->class,
                $propertyName,
            );
            $property->setRawValueWithoutLazyInitialization($proxy, $value);
        }

        return $proxy;
    }
}
