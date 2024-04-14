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
    public const FUNCTION_PREFIX = 'func';

    /**
     * Prefix for properties interceptor
     */
    public const PROPERTY_PREFIX = 'prop';

    /**
     * Prefix for method interceptor
     */
    public const METHOD_PREFIX = 'method';

    /**
     * Prefix for static method interceptor
     */
    public const STATIC_METHOD_PREFIX = 'static';

    /**
     * Trait introduction prefix
     */
    public const INTRODUCTION_TRAIT_PREFIX = 'trait';

    /**
     * Interface introduction prefix
     */
    public const INTRODUCTION_INTERFACE_PREFIX = 'interface';

    /**
     * Initialization prefix, is used for initialization pointcuts
     */
    public const INIT_PREFIX = 'init';

    /**
     * Initialization prefix, is used for initialization pointcuts
     */
    public const STATIC_INIT_PREFIX = 'staticinit';

    /**
     * Suffix, that will be added to all proxied class names
     */
    public const AOP_PROXIED_SUFFIX = '__AopProxied';

    /**
     * Returns a service from the container.
     *
     * Supports lazy-initialization if value is defined as a closure, it will be invoked once to perform initialization.
     *
     * @param class-string<T> $className Class-name of service to retrieve from the container
     * @return object&T
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
     * @return mixed
     * @throws OutOfBoundsException if key was not found
     */
    public function getValue(string $key): mixed;

    /**
     * Checks if item with specified id is present in the container
     */
    public function has(string $id): bool;

    /**
     * Register an aspect in the container
     */
    public function registerAspect(Aspect $aspect): void;

    /**
     * Checks if there are any file resources with changes after since given timestamp
     *
     * @return bool Whether or not there are new changes (filemtime of any resource is greater than given)
     */
    public function hasAnyResourceChangedSince(int $timestamp): bool;

    /**
     * Adds a new item into the container
     *
     * @param mixed $value Value to store
     */
    public function add(string $id, mixed $value): void;
}
