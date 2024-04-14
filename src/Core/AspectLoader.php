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

use Go\Aop\Advisor;
use Go\Aop\Aspect;
use Go\Aop\Pointcut;
use ReflectionClass;

/**
 * Loader of aspects into the container
 */
class AspectLoader
{
    /**
     * @var AspectLoaderExtension[] List of aspect loaders
     */
    protected readonly array $aspectLoaders;

    /**
     * @var class-string[] List of aspect class names that have been loaded
     */
    protected array $loadedAspects = [];

    /**
     * Loader constructor
     */
    public function __construct(
        protected AspectContainer $container,
        AspectLoaderExtension ...$aspectLoaders,
    ) {
        $this->aspectLoaders = $aspectLoaders;
    }

    /**
     * Loads an aspect with the help of aspect loaders, but don't register it in the container
     *
     * @see loadAndRegister() method for registration
     *
     * @return Pointcut[]|Advisor[]
     */
    public function load(Aspect $aspect): array
    {
        $refAspect   = new ReflectionClass($aspect);
        $loadedItems = [];

        foreach ($this->aspectLoaders as $loader) {
            $loadedItems += $loader->load($aspect, $refAspect);
        }

        return $loadedItems;
    }

    /**
     * Loads and register all items of aspect in the container
     */
    public function loadAndRegister(Aspect $aspect): void
    {
        $loadedItems = $this->load($aspect);
        foreach ($loadedItems as $itemId => $item) {
            $this->container->add($itemId, $item);
        }
        $this->loadedAspects[$aspect::class] = $aspect::class;
    }

    /**
     * Returns list of unloaded aspects in the container
     *
     * @return Aspect[]
     */
    public function getUnloadedAspects(): array
    {
        $unloadedAspects = [];

        foreach ($this->container->getServicesByInterface(Aspect::class) as $aspect) {
            if (!isset($this->loadedAspects[$aspect::class])) {
                $unloadedAspects[] = $aspect;
            }
        }

        return $unloadedAspects;
    }
}
