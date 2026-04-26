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

use AllowDynamicProperties;
use RuntimeException;
use Go\Aop\Advisor;
use Go\Aop\Aspect;
use Go\Aop\Pointcut;
use ReflectionClass;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Cached loader is responsible for faster initialization of pointcuts/advisors for concrete aspect
 *
 * @property AspectLoader $loader
 * @phpstan-import-type KernelOptions from AspectKernel
 */
#[AllowDynamicProperties]
class CachedAspectLoader extends AspectLoader
{
    /**
     * Path to the cache directory
     */
    protected ?string $cacheDir = null;

    /**
     * File mode for the cache files
     */
    protected int $cacheFileMode;

    /**
     * Symfony Filesystem instance for all file operations
     */
    protected Filesystem $filesystem;

    /**
     * Identifier of original loader
     *
     * @var class-string<AspectLoader>
     */
    protected string $loaderId;

    /**
     * Cached loader constructor
     *
     * @param class-string<AspectLoader> $loaderId
     * @phpstan-param KernelOptions $options List of kernel options
     */
    public function __construct(AspectContainer $container, string $loaderId, array $options, Filesystem $filesystem)
    {
        $this->cacheDir      = $options['cacheDir'];
        $this->cacheFileMode = $options['cacheFileMode'];
        $this->loaderId      = $loaderId;
        $this->container     = $container;
        $this->filesystem    = $filesystem;
    }

    public function load(Aspect $aspect): array
    {
        if ($this->cacheDir === null || $this->cacheDir === '') {
            return $this->loader->load($aspect);
        }

        $refAspect = new ReflectionClass($aspect);
        $fileName  = $this->cacheDir . '/_aspect/' . sha1($refAspect->getName());

        // If cache is present and actual, then use it
        $aspectFileName = $refAspect->getFileName();
        if ($aspectFileName !== false && $this->filesystem->exists($fileName) && filemtime($fileName) >= filemtime($aspectFileName)) {
            $loadedItems = $this->loadFromCache($fileName);
        } else {
            $loadedItems = $this->loader->load($aspect);
            $this->saveToCache($loadedItems, $fileName);
        }

        return $loadedItems;
    }

    public function __get(string $name): AspectLoader
    {
        if ($name === 'loader') {
            $this->loader = $this->container->getService($this->loaderId);

            return $this->loader;
        }
        throw new RuntimeException('Not implemented');
    }


    /**
     * Loads pointcuts and advisors from the file
     *
     * @return array<string, Pointcut|Advisor>
     */
    protected function loadFromCache(string $fileName): array
    {
        $content = $this->filesystem->readFile($fileName);
        $loadedItems = unserialize($content);

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
        $content = serialize($items);
        $this->filesystem->mkdir(dirname($fileName), $this->cacheFileMode);
        $this->filesystem->dumpFile($fileName, $content);
        $this->filesystem->chmod($fileName, $this->cacheFileMode & (~0111));
    }
}
