<?php

declare(strict_types = 1);
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
use OutOfBoundsException;
use Go\Aop\Aspect;

/**
 * Aspect container interface
 */
interface AspectContainer
{
    /**
     * Prefix for function interceptor
     */
    public const string FUNCTION_PREFIX = 'func';

    /**
     * Prefix for properties interceptor
     */
    public const string PROPERTY_PREFIX = 'prop';

    /**
     * Prefix for method interceptor
     */
    public const string METHOD_PREFIX = 'method';

    /**
     * Prefix for static method interceptor
     */
    public const string STATIC_METHOD_PREFIX = 'static';

    /**
     * Trait introduction prefix
     */
    public const string INTRODUCTION_TRAIT_PREFIX = 'trait';

    /**
     * Interface introduction prefix
     */
    public const string INTRODUCTION_INTERFACE_PREFIX = 'interface';

    /**
     * Initialization prefix, is used for initialization pointcuts
     */
    public const string INIT_PREFIX = 'init';

    /**
     * Initialization prefix, is used for initialization pointcuts
     */
    public const string STATIC_INIT_PREFIX = 'staticinit';

    /**
     * Suffix, that will be added to all proxied class names
     */
    public const string AOP_PROXIED_SUFFIX = '__AopProxied';

    /**
     * Returns a service from the container.
     *
     * Services registered via addLazyService() are returned as typed, instanceof-correct
     * native lazy objects where possible; their factory runs on first actual use.
     *
     * @param class-string<T> $className Class-name of service to retrieve from the container
     * @return T
     *
     * @template T of object
     *
     * @throws OutOfBoundsException if service was not found
     */
    public function getService(string $className): object;

    /**
     * Return list of services tagged with marker interface
     *
     * @param class-string<T> $interfaceTagClassName Interface name of services to retrieve from the container
     * @return T[]
     *
     * @template T
     */
    public function getServicesByInterface(string $interfaceTagClassName): array;

    /**
     * Returns a value from the container
     *
     * @param string $key Given key
     *
     * @return ($key is class-string<T> ? T : mixed)
     * @throws OutOfBoundsException if key was not found
     *
     * @template T of object
     */
    public function getValue(string $key): mixed;

    /**
     * Checks if item with specified id is present in the container
     */
    public function has(string $id): bool;

    /**
     * Register an aspect in the container
     *
     * Passing an aspect instance registers it immediately, exactly as before.
     *
     * Passing a class-name defers construction until the aspect is first needed (first
     * advice hit, or first real interaction with the lazy object handed out during aspect
     * enumeration on the weaving path), keeping it off the hot boot path.
     * An aspect with required constructor arguments must also pass a factory closure that
     * creates the instance; without a factory the class must be default-constructible,
     * which is validated when the aspect materializes into its lazy object.
     *
     * @param Aspect|class-string<Aspect> $aspectOrClassName Aspect instance or its class-name
     * @param null|Closure(AspectContainer $container): Aspect $aspectFactory Factory for deferred
     *        construction (only allowed together with a class-name)
     */
    public function registerAspect(Aspect|string $aspectOrClassName, ?Closure $aspectFactory = null): void;

    /**
     * Checks if all tracked file resources are still fresh at the given timestamp
     *
     * @internal Used by the framework itself to decide whether cached woven code is still valid
     *
     * @return bool True when no resource was modified after the given timestamp (file modification time of every resource is less than or equal to given)
     */
    public function isFreshSince(int $timestamp): bool;

    /**
     * Adds a new item into the container
     *
     * @param string|class-string $id Identifier of value to store, either string literal or class-name
     * @param mixed $value Value to store
     */
    public function add(string $id, mixed $value): void;

    /**
     * Adds a deferred service definition to the container.
     *
     * Nothing is autoloaded or constructed at registration time. On the first retrieval of
     * the service (or when the service matches a getServicesByInterface() query) the entry
     * materializes as a native lazy object of the service class, and the factory closure is
     * invoked once, on the first actual interaction with that object. For classes that PHP
     * cannot make lazy the factory is invoked at materialization time instead.
     *
     * @param class-string<T> $id Identifier of value to store, must be equal to the class-name
     * @param Closure(AspectContainer $container): T $lazyInitializationClosure
     *
     * @template T of object
     */
    public function addLazyService(string $id, Closure $lazyInitializationClosure): void;
}
