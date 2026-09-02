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
    private const string CACHE_FILE_NAME = '/_transformation.cache';

    /**
     * Name of the file with the minimal runtime include map (originalPath => cacheUri|null)
     */
    private const string INCLUDE_MAP_FILE_NAME = '/_include.cache';

    /** @phpstan-var KernelOptions */
    protected array $options;

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
     * Minimal runtime map of woven class name to its cached file, integrateable
     * directly into the composer loader via ClassLoader::addClassMap()
     *
     * @var array<class-string, string>
     */
    protected array $classMap = [];

    /**
     * Set of class names that are known to the cache but were not transformed,
     * so the autoloader can serve them natively without any filtering
     *
     * @var array<class-string, true>
     */
    protected array $skippedClasses = [];

    /**
     * Class names discovered by the weaver per original file, pending until
     * setCacheState() folds them into the metadata record
     *
     * @var array<string, list<class-string>>
     */
    private array $pendingClasses = [];

    /**
     * New metadata items, that was not present in $cacheState
     *
     * @var array<string, mixed>
     */
    protected array $newCacheState = [];

    public function __construct(protected readonly AspectKernel $kernel)
    {
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
                $includeData = include $this->cacheDir . self::INCLUDE_MAP_FILE_NAME;
                if (is_array($includeData)) {
                    $rawClassMap = is_array($includeData['map'] ?? null) ? $includeData['map'] : [];
                    foreach ($rawClassMap as $className => $cacheUri) {
                        if (is_string($className) && is_string($cacheUri)) {
                            /** @var class-string $className */
                            $this->classMap[$className] = $cacheUri;
                        }
                    }
                    $rawSkip = is_array($includeData['skip'] ?? null) ? $includeData['skip'] : [];
                    foreach (array_keys($rawSkip) as $className) {
                        if (is_string($className)) {
                            /** @var class-string $className */
                            $this->skippedClasses[$className] = true;
                        }
                    }
                }
            } elseif (file_exists($this->cacheDir . self::CACHE_FILE_NAME)) {
                // Legacy cache directory (pre-class-map format): the metadata records carry
                // no class names, so the cache cannot serve the class map. Treat the whole
                // cache as stale - everything re-weaves once and both files are rewritten
                // in the new format (or run `cache:warmup:aop` at deploy time).
                $this->cacheStateLoaded = true;
            }
        }

        // Flush pending cache records while the runtime environment is still fully
        // intact: object destruction order during shutdown is unspecified, so relying
        // on __destruct() alone can run the write after collaborators are torn down
        register_shutdown_function($this->flushSilently(...));
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
     * Returns the runtime map of woven class names to their cached files
     *
     * Suitable for direct integration into composer via ClassLoader::addClassMap().
     * Unlike queryCacheState(), this accessor never materializes the full metadata array.
     *
     * @return array<class-string, string>
     */
    public function queryClassMap(): array
    {
        return $this->classMap;
    }

    /**
     * Returns the set of class names known to the cache but not transformed
     *
     * The autoloader serves these natively, without any include-path filtering.
     *
     * @return array<class-string, true>
     */
    public function querySkippedClasses(): array
    {
        return $this->skippedClasses;
    }

    /**
     * Records a class name discovered by the weaver in the given original file
     *
     * The pending names are folded into the file's metadata record by setCacheState()
     * and become the runtime class map / skip set on flush.
     *
     * @param class-string $className
     */
    public function registerClassForResource(string $resource, string $className): void
    {
        $this->pendingClasses[$resource][] = $className;
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
     * Returns cache path for requested file name, or null when caching is disabled
     */
    public function getCachePathForResource(string $resource): ?string
    {
        if (!$this->cacheDir) {
            return null;
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
        $classNames = $this->pendingClasses[$resource] ?? [];
        unset($this->pendingClasses[$resource]);
        $metadata['classes'] = $classNames;

        $this->newCacheState[$resource] = $metadata;

        // Keep the in-memory runtime map coherent within this request
        $cacheUri = $metadata['cacheUri'] ?? null;
        foreach ($classNames as $className) {
            if (is_string($cacheUri)) {
                $this->classMap[$className] = $cacheUri;
                unset($this->skippedClasses[$className]);
            } else {
                $this->skippedClasses[$className] = true;
                unset($this->classMap[$className]);
            }
        }
    }

    /**
     * Automatic destructor saves all new changes into the cache
     *
     * Safety net for managers released before shutdown; the shutdown function
     * registered in the constructor has usually flushed already, making this a no-op.
     * This implementation is not thread-safe, so be care
     */
    public function __destruct()
    {
        $this->flushSilently();
    }

    /**
     * Flushes without ever propagating an error out of shutdown/destruction
     *
     * Losing one cache write is recoverable (the next request simply re-weaves);
     * an exception escaping a destructor or shutdown function is not.
     */
    private function flushSilently(): void
    {
        try {
            $this->flushCacheState();
        } catch (\Throwable) {
            // Deliberately swallowed, see above
        }
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

            $classMap       = [];
            $skippedClasses = [];
            foreach ($fullCacheMap as $metadata) {
                if (!is_array($metadata)) {
                    continue;
                }
                $cacheUri   = $metadata['cacheUri'] ?? null;
                $classNames = is_array($metadata['classes'] ?? null) ? $metadata['classes'] : [];
                foreach ($classNames as $className) {
                    if (!is_string($className)) {
                        continue;
                    }
                    /** @var class-string $className */
                    if (is_string($cacheUri)) {
                        $classMap[$className] = $cacheUri;
                    } else {
                        $skippedClasses[$className] = true;
                    }
                }
            }

            $this->writeCacheFile(self::CACHE_FILE_NAME, $fullCacheMap);
            $this->writeCacheFile(self::INCLUDE_MAP_FILE_NAME, ['map' => $classMap, 'skip' => $skippedClasses]);

            $this->cacheState     = $fullCacheMap;
            $this->classMap       = $classMap;
            $this->skippedClasses = $skippedClasses;
            $this->newCacheState  = [];
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
        $this->classMap         = [];
        $this->skippedClasses   = [];
        $this->pendingClasses   = [];
        $this->newCacheState    = [];

        $this->flushCacheState(true);
    }
}
