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
use Go\Aop\Exception\ContainerException;
use Go\Aop\Exception\InvalidConfigurationException;
use Go\Aop\Exception\ServiceNotFoundException;
use Psr\Container\ContainerInterface;
use ReflectionClass;

/**
 * DI-container, PSR-11 compatible
 */
final class Container implements AspectContainer
{
    /**
     * @var array<string, mixed> Hashmap of items/services in the container
     */
    private array $values = [];

    /**
     * Container that ids unknown here are looked up in
     */
    private ?ContainerInterface $delegate = null;

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
     * Results of getServicesByInterface(), valid until the next registration
     *
     * @var array<class-string, array<string, mixed>>
     */
    private array $servicesByInterface = [];

    /**
     * Incremented by every registration, a cached lookup is reused only while it is unchanged
     */
    private int $registrationVersion = 0;

    /**
     * Newest modification time of the resources that were already stat-ed by {@see self::isFreshSince()}
     */
    private int $cachedMaxTimestamp = 0;

    /**
     * Resources not stat-ed yet: a new resource only costs its own stat, the known ones are never read again
     *
     * @var array<string, string>
     */
    private array $unmeasuredResources;

    /**
     * Classes of the added objects that were queued for resource tracking already
     *
     * @var array<class-string, true>
     */
    private array $trackedResourceClasses = [];

    /**
     * @var array<string, string> Hashmap of resources for application
     */
    private array $resources;

    /**
     * Classes of the added objects, their files become resources on the next freshness check
     *
     * @var array<class-string, true>
     */
    private array $pendingResourceClasses = [];

    /**
     * Constructor for container
     *
     * @param list<string> $resources [Optional] List of additional resources to track for container invalidation
     */
    public function __construct(array $resources = [])
    {
        $this->resources = $this->unmeasuredResources = array_combine($resources, $resources);
    }

    public function onRegistration(string $interfaceName, Closure $listener): void
    {
        $this->registrationListeners[$interfaceName][] = $listener;
    }

    public function add(string $id, mixed $value): void
    {
        $this->values[$id] = $value;
        $this->registrationVersion++;
        $this->servicesByInterface = [];

        if (is_object($value) && !$value instanceof Closure) {
            foreach (class_implements($value) as $interfaceTagName) {
                $this->tags[$interfaceTagName][] = $id;
            }
            // The file of the class is tracked on the next freshness check: registration on every
            // request needs neither reflection nor a file system call
            // A class seen before adds nothing new: its file is tracked or stays untrackable
            $this->trackedResourceClasses[$value::class] ??= $this->pendingResourceClasses[$value::class] = true;
        }
    }

    public function addLazyService(string $id, Closure $lazyInitializationClosure): void
    {
        // Only class-names are acceptable ids here: getServicesByInterface() probes these
        // keys with is_subclass_of(), so an arbitrary string id must be rejected upfront
        // (checked syntactically to avoid autoloading anything at registration time).
        if (preg_match('/^\\\\?[A-Za-z_\x80-\xff][\w\x80-\xff]*(\\\\[A-Za-z_\x80-\xff][\w\x80-\xff]*)*$/', $id) !== 1) {
            throw InvalidConfigurationException::invalidLazyServiceId($id);
        }
        $this->factories[$id] = $lazyInitializationClosure;
        $this->registrationVersion++;
        $this->servicesByInterface = [];

        // With no listeners registered (the production configuration) this is a no-op. Nothing
        // below autoloads: only an id of an already loaded class can be matched exactly, an id
        // that is not loaded yet reaches the listeners as a candidate (see onRegistration())
        $isLoaded = null;
        foreach ($this->registrationListeners as $interfaceName => $listeners) {
            $isLoaded ??= class_exists($id, false) || interface_exists($id, false);
            if (!$isLoaded || is_subclass_of($id, $interfaceName)) {
                foreach ($listeners as $listener) {
                    $listener($id, $this);
                }
            }
        }
    }

    public function getService(string $className): object
    {
        $service = $this->get($className);
        if (!$service instanceof $className) {
            throw ContainerException::serviceNotProperlyRegistered($className);
        }

        return $service;
    }

    public function get(string $id): mixed
    {
        // isset() first for speed, array_key_exists() keeps a stored null value retrievable
        if (isset($this->values[$id]) || array_key_exists($id, $this->values)) {
            return $this->values[$id];
        }
        if (isset($this->factories[$id])) {
            $this->materializeService($id);

            return $this->values[$id];
        }
        if ($this->delegate !== null) {
            // Not stored here: the delegate owns the lifecycle of its services
            return $this->delegate->get($id);
        }

        throw ServiceNotFoundException::valueNotDefined($id);
    }

    public function has(string $id): bool
    {
        return isset($this->values[$id])
            || array_key_exists($id, $this->values)
            || isset($this->factories[$id])
            || ($this->delegate?->has($id) ?? false);
    }

    public function setDelegate(ContainerInterface $delegate): void
    {
        if ($delegate === $this) {
            throw InvalidConfigurationException::containerIsOwnDelegate();
        }
        $this->delegate = $delegate;
    }

    public function getServicesByInterface(string $interfaceTagClassName): array
    {
        // The weaver asks for the same interfaces for every woven file: reuse the last result
        // as long as nothing was registered since
        if (isset($this->servicesByInterface[$interfaceTagClassName])) {
            return $this->servicesByInterface[$interfaceTagClassName];
        }

        // Deferred services are only tagged once materialized (as lazy objects), so
        // materialize the pending ones that implement the requested interface first.
        // This path is only taken during weaving/console runs, never on a hot request.
        // The is_subclass_of() autoload (and an eager fallback factory) can re-enter
        // this method (an aspect class autoloaded here goes through the weaving
        // pipeline, which enumerates aspects again), consuming pending factories from
        // under this loop - hence the existence re-check and the tolerant materialization.
        // A class that is still being autoloaded higher up the stack is not a subclass of
        // anything yet: such an inconclusive probe makes the result unsuitable for caching.
        $isConclusive = true;
        foreach (array_keys($this->factories) as $id) {
            if (!array_key_exists($id, $this->factories)) {
                continue;
            }
            if (is_subclass_of($id, $interfaceTagClassName)) {
                $this->materializeService($id);
            } elseif (!class_exists($id, false) && !interface_exists($id, false)) {
                $isConclusive = false;
            }
        }

        $version = $this->registrationVersion;
        $values  = [];
        foreach (($this->tags[$interfaceTagClassName] ?? []) as $containerKey) {
            $values[$containerKey] = $this->get($containerKey);
        }
        // A re-entrant registration while collecting the values invalidates this result
        if ($isConclusive && $version === $this->registrationVersion) {
            $this->servicesByInterface[$interfaceTagClassName] = $values;
        }

        return $values;
    }

    public function getServiceIdsByInterface(string $interfaceTagClassName): array
    {
        $ids = $this->tags[$interfaceTagClassName] ?? [];
        // A snapshot of the ids: the is_subclass_of() autoload can re-enter the container (see above)
        foreach (array_keys($this->factories) as $id) {
            if (is_subclass_of($id, $interfaceTagClassName) && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
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

        // The proxy is marked while its factory runs: an advice of an aspect that runs while the aspect is created
        // is reported by the interceptor ({@see LazyServiceCreation})
        return NativeLazyProxy::tryCreate($id, function (object $proxy) use ($id, $factory): object {
            $instance = LazyServiceCreation::run($proxy, fn(): object => $factory($this));
            if (!$instance instanceof $id) {
                throw ContainerException::serviceNotProperlyRegistered($id);
            }

            return $instance;
        }) ?? $factory($this);
    }

    public function isFreshSince(int $timestamp): bool
    {
        if ($this->pendingResourceClasses !== []) {
            foreach (array_keys($this->pendingResourceClasses) as $className) {
                $fileName = new ReflectionClass($className)->getFileName();
                if (is_string($fileName)) {
                    $this->addResource($fileName);
                }
            }
            $this->pendingResourceClasses = [];
        }
        if ($this->unmeasuredResources !== []) {
            $this->cachedMaxTimestamp = max(
                array_filter(array_map(filemtime(...), $this->unmeasuredResources)) + [$this->cachedMaxTimestamp],
            );
            $this->unmeasuredResources = [];
        }

        return $this->cachedMaxTimestamp <= $timestamp;
    }

    public function addResource(string $resource): void
    {
        if (!isset($this->resources[$resource]) && is_readable($resource)) {
            $this->resources[$resource] = $resource;
            $this->unmeasuredResources[$resource] = $resource;
        }
    }
}
