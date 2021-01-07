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

use function get_class;

/**
 * Loader of aspects into the container
 */
class AspectLoader
{
    /**
     * Aspect container instance
     */
    protected AspectContainer $container;

    /**
     * List of aspect loaders
     *
     * @var AspectLoaderExtension[]
     */
    protected array $loaders = [];

    /**
     * List of aspect class names that have been loaded
     *
     * @var string[]
     */
    protected array $loadedAspects = [];

    /**
     * Loader constructor
     */
    public function __construct(AspectContainer $container)
    {
        $this->container = $container;
    }

    /**
     * Register an aspect loader extension
     *
     * This method allows to extend the logic of aspect loading by registering an extension for loader.
     */
    public function registerLoaderExtension(AspectLoaderExtension $loader): void
    {
        $this->loaders[] = $loader;
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

        foreach ($this->loaders as $loader) {
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
            if ($item instanceof Pointcut) {
                $this->container->registerPointcut($item, $itemId);
            }
            if ($item instanceof Advisor) {
                $this->container->registerAdvisor($item, $itemId);
            }
        }
        $aspectClass = get_class($aspect);

        $this->loadedAspects[$aspectClass] = $aspectClass;
    }

    /**
     * Returns list of unloaded aspects in the container
     *
     * @return Aspect[]
     */
    public function getUnloadedAspects(): array
    {
        $unloadedAspects = [];

        foreach ($this->container->getByTag('aspect') as $aspect) {
            if (!isset($this->loadedAspects[get_class($aspect)])) {
                $unloadedAspects[] = $aspect;
            }
        }

        return $unloadedAspects;
    }
}
