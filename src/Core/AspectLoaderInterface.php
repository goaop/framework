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
     * @param class-string<Aspect> $aspectClass
     *
     * @return array<string, Pointcut|Advisor>
     */
    public function load(string $aspectClass): array;

    /**
     * Loads and register all items of aspect in the container
     *
     * @param class-string<Aspect> $aspectClass
     */
    public function loadAndRegister(string $aspectClass): void;

    /**
     * Returns the classes of the aspects registered in the container that are not loaded yet
     *
     * @return list<class-string<Aspect>>
     */
    public function getUnloadedAspects(): array;
}
