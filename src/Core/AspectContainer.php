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
use Go\Aop\Advisor;
use Go\Aop\Aspect;
use Go\Aop\Pointcut;

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
     * Return a service or value from the container
     *
     * @return mixed
     * @throws OutOfBoundsException if service was not found
     */
    public function get(string $id);

    /**
     * Return list of service tagged with marker
     */
    public function getByTag(string $tag): array;

    /**
     * Returns a pointcut by identifier
     */
    public function getPointcut(string $id): Pointcut;

    /**
     * Checks if item with specified id is present in the container
     */
    public function has(string $id): bool;

    /**
     * Store the pointcut in the container
     */
    public function registerPointcut(Pointcut $pointcut, string $id): void;

    /**
     * Returns an advisor by identifier
     */
    public function getAdvisor(string $id): Advisor;

    /**
     * Store the advisor in the container
     */
    public function registerAdvisor(Advisor $advisor, string $id): void;

    /**
     * Register an aspect in the container
     */
    public function registerAspect(Aspect $aspect): void;

    /**
     * Returns an aspect by id or class name
     */
    public function getAspect(string $aspectName): Aspect;

    /**
     * Add an AOP resource to the container
     * Resources is used to check the freshness of AOP cache
     *
     * @param string $resource Path to the resource
     */
    public function addResource(string $resource);

    /**
     * Returns the list of AOP resources
     */
    public function getResources(): array;

    /**
     * Checks the freshness of AOP cache
     *
     * @return bool Whether or not concrete file is fresh
     */
    public function isFresh(int $timestamp): bool;

    /**
     * Set a service into the container
     *
     * @param mixed $value Value to store
     */
    public function set(string $id, $value, array $tags = []): void;
}
