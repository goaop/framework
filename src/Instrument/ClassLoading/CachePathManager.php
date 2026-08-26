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
     * Name of the file with full transformation metadata (build-time data, loaded lazily)
     */
    private const CACHE_FILE_NAME = '/_transformation.cache';

    /**
     * Name of the file with the minimal runtime include map (originalPath => cacheUri|null)
     */
    private const INCLUDE_MAP_FILE_NAME = '/_include.cache';

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
     * Loaded lazily from the metadata file: only the cache-miss/weaving paths need it,
     * a hot request works from the include map alone.
     *
     * @var array<string, mixed>
     */
    protected array $cacheState = [];

    /**
     * Whether the full transformation metadata was already loaded from its file
     */
    private bool $cacheStateLoaded = false;

    /**
     * Minimal runtime map of original file path to its cached counterpart
     * (null value = file is known but was not transformed)
     *
     * @var array<string, string|null>
     */
    protected array $includeMap = [];

    /**
     * New metadata items, that was not present in $cacheState
     *
     * @var array<string, mixed>
     */
    protected array $newCacheState = [];

    public function __construct(AspectKernel $kernel)
    {
        $this->kernel   = $kernel;
        $options        = $kernel->getOptions();
        $this->options  = $options;
        $this->appDir   = $options['appDir'];
        $this->cacheDir = $options['cacheDir'];
        $this->fileMode = $options['cacheFileMode'];

        if ($this->cacheDir) {
            // With a prebuilt cache the directory is guaranteed to exist (built at deploy
            // time), so all directory/writability stat checks are skipped - this also
            // covers read-only file systems (GAE, phar, etc)
            if (!$this->kernel->hasFeature(Features::PREBUILT_CACHE)) {
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
                if (!is_writable($this->cacheDir)) {
                    throw new InvalidArgumentException("Cache directory {$this->cacheDir} is not writable");
                }
            }

            if (file_exists($this->cacheDir . self::INCLUDE_MAP_FILE_NAME)) {
                $includeMap = include $this->cacheDir . self::INCLUDE_MAP_FILE_NAME;
                if (is_array($includeMap)) {
                    foreach ($includeMap as $originalPath => $cacheUri) {
                        if (is_string($originalPath)) {
                            $this->includeMap[$originalPath] = is_string($cacheUri) ? $cacheUri : null;
                        }
                    }
                }
            } elseif (file_exists($this->cacheDir . self::CACHE_FILE_NAME)) {
                // Legacy cache directory (pre-split format): derive the include map from
                // the full metadata once; the next flush writes both files
                $this->loadCacheState();
                foreach ($this->cacheState as $originalPath => $metadata) {
                    $cacheUri = is_array($metadata) ? ($metadata['cacheUri'] ?? null) : null;
                    $this->includeMap[$originalPath] = is_string($cacheUri) ? $cacheUri : null;
                }
            }
        }
    }

    /**
     * Loads the full transformation metadata from its file on first demand
     */
    private function loadCacheState(): void
    {
        if ($this->cacheStateLoaded) {
            return;
        }
        $this->cacheStateLoaded = true;

        if ($this->cacheDir !== null && file_exists($this->cacheDir . self::CACHE_FILE_NAME)) {
            $cacheData = include $this->cacheDir . self::CACHE_FILE_NAME;
            if (is_array($cacheData)) {
                $this->cacheState = $cacheData;
            }
        }
    }

    /**
     * Returns the minimal runtime map of original file paths to their cached counterparts
     *
     * A null value means the file is known to the cache but was not transformed. Unlike
     * queryCacheState(), this accessor never materializes the full metadata array.
     *
     * @return array<string, string|null>
     */
    public function queryIncludeMap(): array
    {
        return $this->includeMap;
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

        $cacheState = $this->queryCacheState($resource);
        if ($cacheState !== null && isset($cacheState['cacheUri']) && is_string($cacheState['cacheUri'])) {
            return $cacheState['cacheUri'];
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
        $this->loadCacheState();

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

        $cacheUri = $metadata['cacheUri'] ?? null;
        $this->includeMap[$resource] = is_string($cacheUri) ? $cacheUri : null;
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
            // The full metadata must be loaded before merging, otherwise entries that were
            // never queried during this request would be dropped from the written file
            $this->loadCacheState();
            $fullCacheMap = $this->newCacheState + $this->cacheState;

            $includeMap = [];
            foreach ($fullCacheMap as $originalPath => $metadata) {
                $cacheUri = is_array($metadata) ? ($metadata['cacheUri'] ?? null) : null;
                $includeMap[$originalPath] = is_string($cacheUri) ? $cacheUri : null;
            }

            $this->writeCacheFile(self::CACHE_FILE_NAME, $fullCacheMap);
            $this->writeCacheFile(self::INCLUDE_MAP_FILE_NAME, $includeMap);

            $this->cacheState    = $fullCacheMap;
            $this->includeMap    = $includeMap;
            $this->newCacheState = [];
        }
    }

    /**
     * Writes one cache file as an opcache-friendly PHP return-array with portable paths
     *
     * @param array<string, mixed> $data
     */
    private function writeCacheFile(string $relativeFileName, array $data): void
    {
        $cachePath = substr(var_export($this->cacheDir, true), 1, -1);
        $rootPath  = substr(var_export($this->appDir, true), 1, -1);
        $cacheData = '<?php return ' . var_export($data, true) . ';';
        $cacheData = strtr(
            $cacheData,
            [
                '\'' . $cachePath => 'AOP_CACHE_DIR . \'',
                '\'' . $rootPath  => 'AOP_ROOT_DIR . \''
            ]
        );
        $fullCacheFileName = $this->cacheDir . $relativeFileName;
        file_put_contents($fullCacheFileName, $cacheData, LOCK_EX);
        // For cache files we don't want executable bits by default
        chmod($fullCacheFileName, $this->fileMode & (~0111));

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($fullCacheFileName, true);
        }
    }

    /**
     * Clear the cache state.
     */
    public function clearCacheState(): void
    {
        $this->cacheState       = [];
        $this->cacheStateLoaded = true;
        $this->includeMap       = [];
        $this->newCacheState    = [];

        $this->flushCacheState(true);
    }
}
