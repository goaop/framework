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
    const FUNCTION_PREFIX = "func";

    /**
     * Prefix for properties interceptor
     */
    const PROPERTY_PREFIX = "prop";

    /**
     * Prefix for method interceptor
     */
    const METHOD_PREFIX = "method";

    /**
     * Prefix for static method interceptor
     */
    const STATIC_METHOD_PREFIX = "static";

    /**
     * Trait introduction prefix
     */
    const INTRODUCTION_TRAIT_PREFIX = "introduction";

    /**
     * Initialization prefix, is used for initialization pointcuts
     */
    const INIT_PREFIX = "init";

    /**
     * Initialization prefix, is used for initialization pointcuts
     */
    const STATIC_INIT_PREFIX = "staticinit";

    /**
     * Suffix, that will be added to all proxied class names
     */
    const AOP_PROXIED_SUFFIX = '__AopProxied';

    /**
     * Return a service or value from the container
     *
     * @param string $id Identifier
     *
     * @return mixed
     * @throws \OutOfBoundsException if service was not found
     */
    public function get(string $id);

    /**
     * Return list of service tagged with marker
     *
     * @param string $tag Tag to select
     * @return array
     */
    public function getByTag(string $tag) : array;

    /**
     * Returns a pointcut by identifier
     *
     * @param string $id Pointcut identifier
     *
     * @return Pointcut
     */
    public function getPointcut(string $id) : Pointcut;

    /**
     * Checks if item with specified id is present in the container
     *
     * @param string $id Identifier
     *
     * @return bool
     */
    public function has(string $id) : bool;

    /**
     * Store the pointcut in the container
     *
     * @param Pointcut $pointcut Instance
     * @param string $id Key for pointcut
     */
    public function registerPointcut(Pointcut $pointcut, string $id);

    /**
     * Returns an advisor by identifier
     *
     * @param string $id Advisor identifier
     *
     * @return Advisor
     */
    public function getAdvisor(string $id) : Advisor;

    /**
     * Store the advisor in the container
     *
     * @param Advisor $advisor Instance
     * @param string $id Key for advisor
     */
    public function registerAdvisor(Advisor $advisor, string $id);

    /**
     * Register an aspect in the container
     *
     * @param Aspect $aspect Instance of concrete aspect
     */
    public function registerAspect(Aspect $aspect);

    /**
     * Returns an aspect by id or class name
     *
     * @param string $aspectName Aspect name
     *
     * @return Aspect
     */
    public function getAspect(string $aspectName) : Aspect;

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
    public function getResources() : array;

    /**
     * Checks the freshness of AOP cache
     *
     * @param integer $timestamp
     *
     * @return bool Whether or not concrete file is fresh
     */
    public function isFresh(int $timestamp) : bool;

    /**
     * Set a service into the container
     *
     * @param string $id Identifier
     * @param mixed $value Value to store
     * @param array $tags Additional tags
     */
    public function set(string $id, $value, array $tags = []);
}
