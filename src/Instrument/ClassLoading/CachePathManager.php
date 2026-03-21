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

namespace Go\Instrument\ClassLoading;

use Go\Aop\Features;
use Go\Core\AspectKernel;
use InvalidArgumentException;

use function function_exists;

/**
 * Class that manages real-code to cached-code paths mapping.
 * Can be extended to get a more sophisticated real-to-cached code mapping
 */
class CachePathManager
{
    /**
     * Name of the file with cache paths
     */
    private const CACHE_FILE_NAME = '/_transformation.cache';

    protected array $options = [];

    /**
     * Aspect kernel instance
     */
    protected AspectKernel $kernel;

    protected ?string $cacheDir = null;

    /**
     * File mode
     */
    protected int $fileMode;

    protected ?string $appDir = null;

    /**
     * Cached metadata for transformation state for the concrete file
     */
    protected array $cacheState = [];

    /**
     * New metadata items, that was not present in $cacheState
     */
    protected array $newCacheState = [];

    public function __construct(AspectKernel $kernel)
    {
        $this->kernel   = $kernel;
        $this->options  = $kernel->getOptions();
        $this->appDir   = $this->options['appDir'];
        $this->cacheDir = $this->options['cacheDir'];
        $this->fileMode = $this->options['cacheFileMode'];

        if ($this->cacheDir) {
            if (!is_dir($this->cacheDir)) {
                $cacheRootDir = dirname($this->cacheDir);
                if (!is_writable($cacheRootDir) || !is_dir($cacheRootDir)) {
                    throw new InvalidArgumentException(
                        "Can not create a directory {$this->cacheDir} for the cache.
                        Parent directory {$cacheRootDir} is not writable or not exist."
                    );
                }
                mkdir($this->cacheDir, $this->fileMode, true);
            }
            if (!$this->kernel->hasFeature(Features::PREBUILT_CACHE) && !is_writable($this->cacheDir)) {
                throw new InvalidArgumentException("Cache directory {$this->cacheDir} is not writable");
            }

            if (file_exists($this->cacheDir . self::CACHE_FILE_NAME)) {
                $this->cacheState = include $this->cacheDir . self::CACHE_FILE_NAME;
            }
        }
    }

    /**
     * Returns current cache directory for aspects, can be null
     */
    public function getCacheDir(): ?string
    {
        return $this->cacheDir;
    }

    /**
     * Configures a new cache directory for aspects
     */
    public function setCacheDir(string $cacheDir): void
    {
        $this->cacheDir = $cacheDir;
    }

    /**
     * Returns cache path for requested file name
     *
     * @return bool|string
     */
    public function getCachePathForResource(string $resource)
    {
        if (!$this->cacheDir) {
            return false;
        }

        return str_replace($this->appDir, $this->cacheDir, $resource);
    }

    /**
     * Tries to return an information for queried resource
     *
     * @param string|null $resource Name of the file or null to get all information
     *
     * @return array|null Information or null if no record in the cache
     */
    public function queryCacheState(?string $resource = null): ?array
    {
        if ($resource === null) {
            return $this->cacheState;
        }

        if (isset($this->newCacheState[$resource])) {
            return $this->newCacheState[$resource];
        }

        if (isset($this->cacheState[$resource])) {
            return $this->cacheState[$resource];
        }

        return null;
    }

    /**
     * Put a record about some resource in the cache
     *
     * This data will be persisted during object destruction
     *
     * @param array $metadata Miscellaneous information about resource
     */
    public function setCacheState(string $resource, array $metadata): void
    {
        $this->newCacheState[$resource] = $metadata;
    }

    /**
     * Automatic destructor saves all new changes into the cache
     *
     * This implementation is not thread-safe, so be care
     */
    public function __destruct()
    {
        $this->flushCacheState();
    }

    /**
     * Flushes the cache state into the file
     */
    public function flushCacheState(bool $force = false): void
    {
        if ((!empty($this->newCacheState) && is_writable($this->cacheDir)) || $force) {
            $fullCacheMap      = $this->newCacheState + $this->cacheState;
            $cachePath         = substr(var_export($this->cacheDir, true), 1, -1);
            $rootPath          = substr(var_export($this->appDir, true), 1, -1);
            $cacheData         = '<?php return ' . var_export($fullCacheMap, true) . ';';
            $cacheData         = strtr(
                $cacheData,
                [
                    '\'' . $cachePath => 'AOP_CACHE_DIR . \'',
                    '\'' . $rootPath  => 'AOP_ROOT_DIR . \''
                ]
            );
            $fullCacheFileName = $this->cacheDir . self::CACHE_FILE_NAME;
            file_put_contents($fullCacheFileName, $cacheData, LOCK_EX);
            // For cache files we don't want executable bits by default
            chmod($fullCacheFileName, $this->fileMode & (~0111));

            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($fullCacheFileName, true);
            }
            $this->cacheState    = $this->newCacheState + $this->cacheState;
            $this->newCacheState = [];
        }
    }

    /**
     * Clear the cache state.
     */
    public function clearCacheState(): void
    {
        $this->cacheState    = [];
        $this->newCacheState = [];

        $this->flushCacheState(true);
    }
}
