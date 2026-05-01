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
 *
 * @phpstan-import-type KernelOptions from AspectKernel
 */
class CachePathManager
{
    /**
     * Name of the file with cache paths
     */
    private const CACHE_FILE_NAME = '/_transformation.cache';

    /** @phpstan-var KernelOptions */
    protected array $options;

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
     *
     * @var array<string, mixed>
     */
    protected array $cacheState = [];

    /**
     * New metadata items, that was not present in $cacheState
     *
     * @var array<string, mixed>
     */
    protected array $newCacheState = [];

    /**
     * Per-request overrides for the woven (trait) file cache path, keyed by original source URI.
     * Populated by WeavingTransformer so that the woven file is written to a PSR-4-compatible
     * <cacheDir>/<Namespace/ClassName__AopProxied>.php path instead of the source-relative path,
     * preventing collisions with the proxy class file when the namespace root equals appDir.
     *
     * @var array<string, string>
     */
    private array $wovenFilePathOverrides = [];

    public function __construct(AspectKernel $kernel)
    {
        $this->kernel   = $kernel;
        $options        = $kernel->getOptions();
        $this->options  = $options;
        $this->appDir   = $options['appDir'];
        $this->cacheDir = $options['cacheDir'];
        $this->fileMode = $options['cacheFileMode'];

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
                $cacheData = include $this->cacheDir . self::CACHE_FILE_NAME;
                if (is_array($cacheData)) {
                    $this->cacheState = $cacheData;
                }
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
     * @return string|false
     */
    public function getCachePathForResource(string $resource)
    {
        if (!$this->cacheDir) {
            return false;
        }

        return $this->appDir !== null
            ? str_replace($this->appDir, $this->cacheDir, $resource)
            : $resource;
    }

    /**
     * Tries to return an information for queried resource
     *
     * @param string|null $resource Name of the file or null to get all information
     *
     * @return array<string, mixed>|null Information or null if no record in the cache
     */
    public function queryCacheState(?string $resource = null): ?array
    {
        if ($resource === null) {
            return $this->cacheState;
        }

        if (isset($this->newCacheState[$resource])) {
            $result = $this->newCacheState[$resource];
            return is_array($result) ? $result : null;
        }

        if (isset($this->cacheState[$resource])) {
            $result = $this->cacheState[$resource];
            return is_array($result) ? $result : null;
        }

        return null;
    }

    /**
     * Put a record about some resource in the cache
     *
     * This data will be persisted during object destruction
     *
     * @param array<string, mixed> $metadata Miscellaneous information about resource
     */
    public function setCacheState(string $resource, array $metadata): void
    {
        $this->newCacheState[$resource] = $metadata;
    }

    /**
     * Registers a PSR-4 woven file path for a given source URI.
     *
     * Called by {@see WeavingTransformer} after weaving a class so that
     * {@see CachingTransformer} stores the trait (woven) file at the correct PSR-4
     * location (<cacheDir>/<Namespace/ClassName__AopProxied>.php) rather than the
     * source-relative path, which would collide with the proxy class file when the
     * PSR-4 namespace root coincides with appDir.
     */
    public function registerWovenFilePath(string $originalUri, string $wovenPath): void
    {
        $this->wovenFilePathOverrides[$originalUri] = $wovenPath;
    }

    /**
     * Returns the registered PSR-4 woven file path for the given source URI, or null if none was set.
     */
    public function getWovenFilePath(string $originalUri): ?string
    {
        return $this->wovenFilePathOverrides[$originalUri] ?? null;
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
        if ((!empty($this->newCacheState) && $this->cacheDir !== null && is_writable($this->cacheDir)) || $force) {
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
