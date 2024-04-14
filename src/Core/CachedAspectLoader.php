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

/**
 * Cached loader is responsible for faster initialization of pointcuts/advisors for concrete aspect
 *
 * @property AspectLoader $loader
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
     * Identifier of original loader
     */
    protected string $loaderId;

    /**
     * Cached loader constructor
     *
     * @param array $options List of kernel options
     */
    public function __construct(AspectContainer $container, string $loaderId, array $options = [])
    {
        $this->cacheDir      = $options['cacheDir'] ?? null;
        $this->cacheFileMode = $options['cacheFileMode'] ?? 0770 & ~umask();
        $this->loaderId      = $loaderId;
        $this->container     = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function load(Aspect $aspect): array
    {
        $refAspect = new ReflectionClass($aspect);
        $fileName  = $this->cacheDir . '/_aspect/' . sha1($refAspect->getName());

        // If cache is present and actual, then use it
        if (file_exists($fileName) && filemtime($fileName) >= filemtime($refAspect->getFileName())) {
            $loadedItems = $this->loadFromCache($fileName);
        } else {
            $loadedItems = $this->loader->load($aspect);
            $this->saveToCache($loadedItems, $fileName);
        }

        return $loadedItems;
    }

    /**
     * {@inheritdoc}
     */
    public function __get($name)
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
     * @return Pointcut[]|Advisor[]
     */
    protected function loadFromCache(string $fileName): array
    {
        $content     = file_get_contents($fileName);
        $loadedItems = unserialize($content);

        return $loadedItems;
    }

    /**
     * Save pointcuts and advisors to the file
     *
     * @param Pointcut[]|Advisor[] $items List of items to store
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
