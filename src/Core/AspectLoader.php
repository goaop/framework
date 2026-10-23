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
 *
 * @internal Framework service, not a public extension point
 */
class AspectLoader implements AspectLoaderInterface
{
    /**
     * @var AspectLoaderExtension[] List of aspect loaders
     */
    private readonly array $aspectLoaders;

    /**
     * @var class-string[] List of aspect class names that have been loaded
     */
    private array $loadedAspects = [];

    /**
     * Loader constructor
     */
    public function __construct(
        private readonly AspectContainer $container,
        AspectLoaderExtension ...$aspectLoaders,
    ) {
        $this->aspectLoaders = $aspectLoaders;
    }

    /**
     * Loads an aspect with the help of aspect loaders, but don't register it in the container
     *
     * @see loadAndRegister() method for registration
     *
     * @param class-string<Aspect> $aspectClass
     *
     * @return array<string, Pointcut|Advisor>
     */
    public function load(string $aspectClass): array
    {
        $refAspect   = new ReflectionClass($aspectClass);
        $loadedItems = [];

        foreach ($this->aspectLoaders as $loader) {
            $loadedItems += $loader->load($refAspect);
        }

        return $loadedItems;
    }

    /**
     * Loads and register all items of aspect in the container
     *
     * @param class-string<Aspect> $aspectClass
     */
    public function loadAndRegister(string $aspectClass): void
    {
        $loadedItems = $this->load($aspectClass);
        foreach ($loadedItems as $itemId => $item) {
            $this->container->add($itemId, $item);
        }
        $this->loadedAspects[$aspectClass] = $aspectClass;
    }

    /**
     * Returns the classes of the aspects registered in the container that are not loaded yet
     *
     * @return list<class-string<Aspect>>
     */
    public function getUnloadedAspects(): array
    {
        $unloadedAspects = [];

        foreach ($this->container->getServiceIdsByInterface(Aspect::class) as $aspectClass) {
            if (!isset($this->loadedAspects[$aspectClass]) && is_subclass_of($aspectClass, Aspect::class)) {
                $unloadedAspects[] = $aspectClass;
            }
        }

        return $unloadedAspects;
    }
}
