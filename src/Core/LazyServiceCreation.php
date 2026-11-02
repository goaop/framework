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
use Go\Aop\Framework\AbstractMethodInvocation;
use Psr\Container\ContainerInterface;

/**
 * Lazy services whose factory is running.
 *
 * A deferred service of the container is handed out as a native lazy proxy, whose factory runs when the proxy
 * is used first. When creating the service runs a method of the proxy again (an aspect whose creation runs one
 * of its own advices), that method runs on an object that is not initialized yet: PHP reports an uninitialized
 * property at best. The interceptors look the bound object of an advice up here before running it.
 *
 * @internal
 */
final class LazyServiceCreation
{
    /**
     * Static facade, never instantiated
     *
     * @codeCoverageIgnore
     */
    private function __construct() {}

    /**
     * Lazy proxies whose factory is running, by object id. Read on every advice call: kept empty while no
     * service is created, so the check is a single comparison
     *
     * @var array<int, object>
     */
    public static array $inProgress = [];

    /**
     * Runs the factory of a lazy proxy, marking the proxy as being created meanwhile
     *
     * @template T of object
     *
     * @param T            $proxy
     * @param Closure(): T $factory
     *
     * @return T
     */
    public static function run(object $proxy, Closure $factory): object
    {
        $id                    = spl_object_id($proxy);
        self::$inProgress[$id] = $proxy;
        try {
            return $factory();
        } finally {
            unset(self::$inProgress[$id]);
        }
    }

    public static function isInProgress(object $object): bool
    {
        return isset(self::$inProgress[spl_object_id($object)]);
    }

    /**
     * Calls of application code made since the creation of the proxy started, outermost first
     *
     * Calls of the framework, of PSR-11 containers and of closures are left out, so are the calls of the
     * original methods of woven classes, which repeat the call of the woven method.
     *
     * @return list<string>
     */
    public static function callsSinceCreationOf(object $proxy): array
    {
        $calls = [];
        foreach (debug_backtrace() as $frame) {
            $class = $frame['class'] ?? null;
            if ($class === self::class && $frame['function'] === 'run' && ($frame['args'][0] ?? null) === $proxy) {
                return array_reverse($calls);
            }
            if ($class === null || !isset($frame['type']) || self::isInfrastructure($class, $frame['function'])) {
                continue;
            }
            $function = $frame['function'];
            if (str_ends_with($function, AbstractMethodInvocation::TRAIT_ALIAS_SUFFIX)) {
                $function = substr($function, 0, -strlen(AbstractMethodInvocation::TRAIT_ALIAS_SUFFIX));
            }
            $call = $class . $frame['type'] . $function . '()';
            if (end($calls) !== $call) {
                $calls[] = $call;
            }
        }

        // Not created through run(): the calls can not be told apart from the ones before the creation
        return [];
    }

    private static function isInfrastructure(string $class, string $function): bool
    {
        foreach (['Go\\Aop\\', 'Go\\Core\\', 'Go\\Instrument\\', 'Go\\Proxy\\'] as $namespace) {
            if (str_starts_with($class, $namespace)) {
                return true;
            }
        }

        return str_starts_with($function, '{closure')
            || $class === Closure::class
            || is_a($class, ContainerInterface::class, true)
            || is_a($class, AspectKernel::class, true);
    }
}
