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

use Go\Aop;

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
     * Return list of service tagged with marker
     *
     * @param string $tag Tag to select
     * @return array
     */
    public function getByTag($tag);

    /**
     * Returns a pointcut by identifier
     *
     * @param string $id Pointcut identifier
     *
     * @return Aop\Pointcut
     */
    public function getPointcut($id);

    /**
     * Store the pointcut in the container
     *
     * @param Aop\Pointcut $pointcut Instance
     * @param string $id Key for pointcut
     */
    public function registerPointcut(Aop\Pointcut $pointcut, $id);

    /**
     * Returns an advisor by identifier
     *
     * @param string $id Advisor identifier
     *
     * @return Aop\Advisor
     */
    public function getAdvisor($id);

    /**
     * Store the advisor in the container
     *
     * @param Aop\Advisor $advisor Instance
     * @param string $id Key for advisor
     */
    public function registerAdvisor(Aop\Advisor $advisor, $id);

    /**
     * Register an aspect in the container
     *
     * @param Aop\Aspect $aspect Instance of concrete aspect
     */
    public function registerAspect(Aop\Aspect $aspect);

    /**
     * Returns an aspect by id or class name
     *
     * @param string $aspectName Aspect name
     *
     * @return Aop\Aspect
     */
    public function getAspect($aspectName);

    /**
     * Add an AOP resource to the container
     *
     * @param string $resource Path to the resource
     * Resources is used to check the freshness of AOP cache
     */
    public function addResource($resource);

    /**
     * Returns the list of AOP resources
     *
     * @return array
     */
    public function getResources();

    /**
     * Checks the freshness of AOP cache
     *
     * @param integer $timestamp
     *
     * @return bool Whether or not concrete file is fresh
     */
    public function isFresh($timestamp);
}
