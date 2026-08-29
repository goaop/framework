<?php

declare(strict_types = 1);
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
 * Cached loader is responsible for faster initialization of pointcuts/advisors for concrete aspect
 *
 * @phpstan-import-type KernelOptions from AspectKernel
 */
class CachedAspectLoader extends AspectLoader
{
    /**
     * Original loader, resolved from the container on first access and memoized in the backing store
     */
    private AspectLoader $loader {
        get => $this->loader ??= $this->container->getService($this->loaderId);
    }

    /**
     * Path to the cache directory
     */
    protected ?string $cacheDir = null;

    /**
     * File mode for the cache files
     */
    protected int $cacheFileMode;

    /**
     * Identifier of original loader
     *
     * @var class-string<AspectLoader>
     */
    protected string $loaderId;

    /**
     * Whether an existing advisor cache file is trusted without freshness checks
     */
    protected bool $isPrebuiltCache = false;

    /**
     * Cached loader constructor
     *
     * @param class-string<AspectLoader> $loaderId
     * @phpstan-param KernelOptions $options List of kernel options
     */
    public function __construct(AspectContainer $container, string $loaderId, array $options)
    {
        $this->cacheDir        = $options['cacheDir'];
        $this->cacheFileMode   = $options['cacheFileMode'];
        $this->loaderId        = $loaderId;
        $this->container       = $container;
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
     * Loads pointcuts and advisors from the file
     *
     * @return array<string, Pointcut|Advisor>
     */
    protected function loadFromCache(string $fileName): array
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
    protected function saveToCache(array $items, string $fileName): void
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
