<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Core;

use Go\Aop\Advisor;
use Go\Aop\Aspect;
use Go\Aop\Features;
use Go\Aop\Pointcut;
use ReflectionClass;

/**
 * Cached loader is a decorator that is responsible for faster initialization
 * of pointcuts/advisors for concrete aspect
 *
 * @phpstan-import-type KernelOptions from AspectKernel
 */
class CachedAspectLoader implements AspectLoaderInterface
{
    /**
     * Original loader, resolved from the container on first access and memoized in the backing store
     */
    private AspectLoaderInterface $loader {
        get => $this->loader ??= $this->container->getService($this->loaderId);
    }

    /**
     * Path to the cache directory
     */
    private readonly ?string $cacheDir;

    /**
     * File mode for the cache files
     */
    private readonly int $cacheFileMode;

    /**
     * Whether an existing advisor cache file is trusted without freshness checks
     */
    private readonly bool $isPrebuiltCache;

    /**
     * @var class-string[] List of aspect class names that have been loaded
     */
    private array $loadedAspects = [];

    /**
     * Cached loader constructor
     *
     * @param class-string<AspectLoaderInterface> $loaderId Identifier of original loader
     * @phpstan-param KernelOptions $options List of kernel options
     */
    public function __construct(
        private readonly AspectContainer $container,
        private readonly string $loaderId,
        array $options,
    ) {
        $this->cacheDir        = $options['cacheDir'];
        $this->cacheFileMode   = $options['cacheFileMode'];
        $this->isPrebuiltCache = ($options['features'] & Features::PREBUILT_CACHE) !== 0;
    }

    public function load(Aspect $aspect): array
    {
        if ($this->cacheDir === null || $this->cacheDir === '') {
            return $this->loader->load($aspect);
        }

        $refAspect = new ReflectionClass($aspect);
        $fileName  = $this->cacheDir . '/_aspect/' . sha1($refAspect->getName());

        // With a prebuilt cache an existing advisor cache file is trusted without any
        // freshness checks; on a corrupt/empty file fall back to the direct loader
        // WITHOUT writing (the file system may be read-only).
        if ($this->isPrebuiltCache && file_exists($fileName)) {
            $loadedItems = $this->loadFromCache($fileName);
            if ($loadedItems !== []) {
                return $loadedItems;
            }

            return $this->loader->load($aspect);
        }

        // If cache is present and actual, then use it
        $aspectFileName = $refAspect->getFileName();
        if ($aspectFileName !== false && file_exists($fileName) && filemtime($fileName) >= filemtime($aspectFileName)) {
            $loadedItems = $this->loadFromCache($fileName);
        } else {
            $loadedItems = $this->loader->load($aspect);
            $this->saveToCache($loadedItems, $fileName);
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
     * @return list<Aspect>
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

    /**
     * Loads pointcuts and advisors from the file
     *
     * @return array<string, Pointcut|Advisor>
     */
    private function loadFromCache(string $fileName): array
    {
        $content = file_get_contents($fileName);
        if ($content === false) {
            return [];
        }
        // A corrupt cache file is handled by the empty-result fallback, so the
        // unserialize() warning carries no information
        $loadedItems = @unserialize($content);

        if (!is_array($loadedItems)) {
            return [];
        }
        /** @var array<string, Pointcut|Advisor> $filtered */
        $filtered = array_filter($loadedItems, fn($item) => $item instanceof Pointcut || $item instanceof Advisor);

        return $filtered;
    }

    /**
     * Save pointcuts and advisors to the file
     *
     * @param array<string, Pointcut|Advisor> $items Array of items to store
     */
    private function saveToCache(array $items, string $fileName): void
    {
        $content       = serialize($items);
        $directoryName = dirname($fileName);
        if (!is_dir($directoryName)) {
            mkdir($directoryName, $this->cacheFileMode, true);
        }
        file_put_contents($fileName, $content, LOCK_EX);
        // For cache files we don't want executable bits by default
        chmod($fileName, $this->cacheFileMode & (~0111));
    }
}
