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

use Go\Aop\Advisor;
use Go\Aop\Aspect;
use Go\Aop\Pointcut;
use ReflectionClass;

/**
 * Contract for loading aspects into the container
 *
 * @internal framework API, not intended for use outside of the framework
 */
interface AspectLoaderInterface
{
    /**
     * Loads an aspect with the help of aspect loaders, but don't register it in the container
     *
     * Only the class is read: the aspect itself is never created, its advices resolve it from the container when
     * they run for the first time.
     *
     * @see loadAndRegister() method for registration
     *
     * @internal
     *
     * @param ReflectionClass<covariant Aspect> $aspect
     *
     * @return array<string, Pointcut|Advisor>
     */
    public function load(ReflectionClass $aspect): array;

    /**
     * Loads and register all items of aspect in the container
     *
     * @internal
     *
     * @param ReflectionClass<covariant Aspect> $aspect
     */
    public function loadAndRegister(ReflectionClass $aspect): void;

    /**
     * Returns the aspects registered in the container that are not loaded yet
     *
     * The reflection of each aspect is created once here and passed on to the loaders.
     *
     * @internal
     *
     * @return list<ReflectionClass<covariant Aspect>>
     */
    public function getUnloadedAspects(): array;
}
