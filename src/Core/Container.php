<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Core;

use Closure;
use InvalidArgumentException;
use OutOfBoundsException;
use ReflectionObject;
use UnexpectedValueException;

/**
 * DI-container
 */
class Container implements AspectContainer
{
    /**
     * @var array<string, mixed> Hashmap of items/services in the container
     */
    private array $values = [];

    /**
     * @var array<class-string, Closure(AspectContainer): object> Deferred service factories, keyed by class-name
     */
    private array $factories = [];

    /**
     * @var array<class-string, list<Closure(class-string, AspectContainer): void>> Registration listeners,
     *      keyed by the interface their deferred service ids are matched against
     */
    private array $registrationListeners = [];

    /**
     * @var array<class-string, list<string>> Holds information about mapping of interface tags into identifiers
     */
    private array $tags = [];

    /**
     * Cached timestamp for resources, might be uninitialized if {@see self::isFreshSince()} is not called yet
     */
    private int $cachedMaxTimestamp;

    /**
     * @var array<string, string> Hashmap of resources for application
     */
    private array $resources;

    /**
     * Constructor for container
     *
     * @param list<string> $resources [Optional] List of additional resources to track for container invalidation
     */
    public function __construct(array $resources = [])
    {
        $this->resources = array_combine($resources, $resources);
    }

    final public function onRegistration(string $interfaceName, Closure $listener): void
    {
        $this->registrationListeners[$interfaceName][] = $listener;
    }

    final public function add(string $id, mixed $value): void
    {
        $this->values[$id] = $value;

        if (is_object($value) && !$value instanceof Closure) {
            $reflectionInstance = new ReflectionObject($value);
            foreach ($reflectionInstance->getInterfaceNames() as $interfaceTagName) {
                $this->tags[$interfaceTagName][] = $id;
            }
            $fileName = $reflectionInstance->getFileName();
            if (is_string($fileName)) {
                $this->addResource($fileName);
            }
        }
    }

    final public function addLazyService(string $id, Closure $lazyInitializationClosure): void
    {
        // Only class-names are acceptable ids here: getServicesByInterface() probes these
        // keys with is_subclass_of(), so an arbitrary string id must be rejected upfront
        // (checked syntactically to avoid autoloading anything at registration time).
        if (preg_match('/^\\\\?[A-Za-z_\x80-\xff][\w\x80-\xff]*(\\\\[A-Za-z_\x80-\xff][\w\x80-\xff]*)*$/', $id) !== 1) {
            throw new InvalidArgumentException("Lazy service id must be a valid class name, \"$id\" given");
        }
        $this->factories[$id] = $lazyInitializationClosure;

        // With no listeners registered (the production configuration) this is a no-op and
        // nothing below autoloads; a registered listener accepts the is_subclass_of()
        // autoload of matching ids as its cost.
        foreach ($this->registrationListeners as $interfaceName => $listeners) {
            if (is_subclass_of($id, $interfaceName)) {
                foreach ($listeners as $listener) {
                    $listener($id, $this);
                }
            }
        }
    }

    final public function getService(string $className): object
    {
        $service = $this->getValue($className);
        if (!$service instanceof $className) {
            throw new UnexpectedValueException("Service $className is not properly registered");
        }

        return $service;
    }

    final public function getValue(string $key): mixed
    {
        if (!isset($this->values[$key])) {
            if (isset($this->factories[$key])) {
                $this->materializeService($key);
            } else {
                throw new OutOfBoundsException("Value $key is not defined in the container");
            }
        }

        return $this->values[$key];
    }

    final public function has(string $id): bool
    {
        return isset($this->values[$id]) || isset($this->factories[$id]);
    }

    final public function getServicesByInterface(string $interfaceTagClassName): array
    {
        // Deferred services are only tagged once materialized (as lazy objects), so
        // materialize the pending ones that implement the requested interface first.
        // This path is only taken during weaving/console runs, never on a hot request.
        // The is_subclass_of() autoload (and an eager fallback factory) can re-enter
        // this method (an aspect class autoloaded here goes through the weaving
        // pipeline, which enumerates aspects again), consuming pending factories from
        // under this loop - hence the existence re-check and the tolerant materialization.
        foreach (array_keys($this->factories) as $id) {
            if (array_key_exists($id, $this->factories) && is_subclass_of($id, $interfaceTagClassName)) {
                $this->materializeService($id);
            }
        }

        $values = [];
        foreach (($this->tags[$interfaceTagClassName] ?? []) as $containerKey) {
            $values[$containerKey] = $this->getValue($containerKey);
        }

        return $values;
    }

    /**
     * Materializes a deferred service into a container entry and tags it by its interfaces.
     *
     * Where the class supports it, the entry becomes a native lazy proxy
     * ({@see NativeLazyProxy}): a typed, instanceof-correct instance of the service class
     * whose factory only runs on first actual interaction with the object. Classes that
     * PHP cannot make lazy and ids that are not loadable classes fall back to invoking
     * the factory eagerly, as before.
     */
    private function materializeService(string $id): void
    {
        $factory = $this->factories[$id] ?? null;
        if ($factory === null) {
            // Already materialized by a re-entrant call (e.g. triggered through autoloading)
            return;
        }
        // Unset before touching the class: autoloading (class_exists/reflection below) or an
        // eager fallback factory can re-enter the container and must not materialize $id twice
        unset($this->factories[$id]);

        $this->add($id, $this->createLazyService($id, $factory));
    }

    /**
     * Creates the container entry for a deferred service: a native lazy proxy when possible,
     * otherwise the eagerly invoked factory result
     *
     * @param Closure(AspectContainer): object $factory
     */
    private function createLazyService(string $id, Closure $factory): object
    {
        if (!class_exists($id)) {
            return $factory($this);
        }

        return NativeLazyProxy::tryCreate($id, function () use ($id, $factory): object {
            $instance = $factory($this);
            if (!$instance instanceof $id) {
                throw new UnexpectedValueException("Service $id is not properly registered");
            }

            return $instance;
        }) ?? $factory($this);
    }

    final public function isFreshSince(int $timestamp): bool
    {
        if (!isset($this->cachedMaxTimestamp)) {
            $this->cachedMaxTimestamp = max(array_filter(array_map(filemtime(...), $this->resources)) + [0]);
        }

        return $this->cachedMaxTimestamp <= $timestamp;
    }

    final public function addResource(string $resource): void
    {
        if (!isset($this->resources[$resource]) && is_readable($resource)) {
            $this->resources[$resource] = $resource;

            // Invalidation of calculated timestamp
            unset($this->cachedMaxTimestamp);
        }
    }
}
