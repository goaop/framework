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
     * @see loadAndRegister() method for registration
     *
     * @return array<string, Pointcut|Advisor>
     */
    public function load(Aspect $aspect): array;

    /**
     * Loads and register all items of aspect in the container
     */
    public function loadAndRegister(Aspect $aspect): void;

    /**
     * Returns list of unloaded aspects in the container
     *
     * @return list<Aspect>
     */
    public function getUnloadedAspects(): array;
}
