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

use Go\Aop\Exception\InvalidConfigurationException;
use Go\Aop\Exception\WeavingException;
use Go\Aop\Features;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\Cache\CacheFileWriter;
use Go\Instrument\PathResolver;

/**
 * Class that manages real-code to cached-code paths mapping.
 *
 * @phpstan-import-type KernelOptions from AspectKernel
 *
 * @internal Framework service, not a public extension point
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

    /**
     * Version of the metadata format of both files, a file of another version is ignored (or rejected with a
     * prebuilt cache, which must never be rebuilt at runtime)
     *
     * Both files are constant arrays: records are keyed by the path of the source relative to the application
     * root, and cached files are written as `__DIR__ . '/…'`. opcache keeps such an array as is, so including
     * the files on a warm request copies and checks nothing.
     */
    public const int FORMAT_VERSION = 3;

    /** @phpstan-var KernelOptions */
    protected array $options;

    protected ?string $cacheDir = null;

    /**
     * File mode
     */
    protected int $fileMode;

    protected ?string $appDir = null;

    /**
     * Writer performing the actual cache file system operations, created on the first write
     */
    private CacheFileWriter $cacheFileWriter;

    /**
     * Cached metadata for transformation state for the concrete file, keyed by {@see self::getRecordKey()}
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
     * New metadata items, that was not present in $cacheState, keyed by {@see self::getRecordKey()}
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
            $hasIncludeMap = file_exists($this->cacheDir . self::INCLUDE_MAP_FILE_NAME);
            // With a prebuilt cache the directory is guaranteed to exist (built at deploy
            // time), so all directory/writability stat checks are skipped - this also
            // covers read-only file systems (GAE, phar, etc). An existing include map
            // proves the directory too: a warm cache costs no stat calls, and a cache
            // that became read-only fails on the next write only, not on every request
            if (!$hasIncludeMap && !$this->kernel->hasFeature(Features::PREBUILT_CACHE)) {
                if (!is_dir($this->cacheDir)) {
                    $cacheRootDir = dirname($this->cacheDir);
                    if (!is_writable($cacheRootDir) || !is_dir($cacheRootDir)) {
                        throw new InvalidConfigurationException(
                            "Can not create a directory {$this->cacheDir} for the cache.
                            Parent directory {$cacheRootDir} is not writable or not exist.",
                        );
                    }
                    mkdir($this->cacheDir, CacheFileWriter::directoryModeFor($this->fileMode), true);
                }
                if (!is_writable($this->cacheDir)) {
                    throw new InvalidConfigurationException("Cache directory {$this->cacheDir} is not writable");
                }
            }

            if ($hasIncludeMap) {
                $includeData = include $this->cacheDir . self::INCLUDE_MAP_FILE_NAME;
                if (!$this->isCurrentFormat($includeData)) {
                    $this->rejectOutdatedFormat();
                    // Outdated format: everything re-weaves once and both files are rewritten
                    $this->cacheStateLoaded = true;
                } else {
                    // A file of the current format is written by flushCacheState() only, so its arrays are used
                    // as they are: checking every entry would cost a loop over the whole map on every request
                    /** @var array{map: array<class-string, string>, skip: array<class-string, true>} $includeData */
                    $this->classMap       = $includeData['map'];
                    $this->skippedClasses = $includeData['skip'];
                }
            } elseif (file_exists($this->cacheDir . self::CACHE_FILE_NAME)) {
                // Legacy cache directory (pre-class-map format): the metadata records carry
                // no class names, so the cache cannot serve the class map. Treat the whole
                // cache as stale - everything re-weaves once and both files are rewritten
                // in the new format (or run `cache:warmup:aop` at deploy time).
                $this->rejectOutdatedFormat();
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
            if (!$this->isCurrentFormat($cacheData)) {
                $this->rejectOutdatedFormat();
            } else {
                /** @var array{files: array<string, mixed>} $cacheData */
                $this->cacheState = $cacheData['files'];
            }
        }
    }

    /**
     * Checks that the data loaded from a metadata file has the current format version
     */
    private function isCurrentFormat(mixed $cacheData): bool
    {
        return is_array($cacheData) && ($cacheData['version'] ?? null) === self::FORMAT_VERSION;
    }

    /**
     * A prebuilt cache is never rebuilt at runtime, so metadata of another format is a deployment error
     */
    private function rejectOutdatedFormat(): void
    {
        if ($this->kernel->hasFeature(Features::PREBUILT_CACHE)) {
            throw new WeavingException(sprintf(
                'The AOP cache in %s was built by another version of the framework, '
                . 'rebuild it with `bin/aspect cache:warmup:aop` before using Features::PREBUILT_CACHE',
                $this->cacheDir,
            ));
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
     * Returns the writer that every cache artefact must be written through (atomic, opcache-aware)
     */
    public function getCacheFileWriter(): CacheFileWriter
    {
        return $this->cacheFileWriter ??= new CacheFileWriter($this->fileMode);
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

        // Resources outside the application root map to themselves (they are never woven)
        return $this->appDir !== null
            ? (PathResolver::rebase($resource, $this->appDir, $this->cacheDir) ?? $resource)
            : $resource;
    }

    /**
     * Returns the cache record of a resource when it is still valid for the current source, null otherwise
     *
     * The single freshness rule of the framework: the stream filter serves a cache hit with it, and the debug-mode
     * autoloader includes a fresh untransformed file by its original path, so that opcache can cache it.
     *
     * @return array<string, mixed>|null Valid record, or null when there is no record or the record is stale
     */
    public function queryFreshCacheState(string $resource, AspectContainer $container): ?array
    {
        $cacheState = $this->queryCacheState($resource);
        if ($cacheState === null) {
            return null;
        }

        return $this->isFreshRecord($cacheState, $resource, $container) ? $cacheState : null;
    }

    /**
     * Decides whether a cache record is still valid for the source it was built from
     *
     * The freshness rule itself, shared by every cache index of the framework (the transformation metadata
     * of the stream driver and the donor index of the z-engine driver), so staleness is decided in one place.
     *
     * @param array<array-key, mixed> $record Cache record carrying `filemtime`, `filesize` and `cachedAt`
     */
    public function isFreshRecord(array $record, string $resource, AspectContainer $container): bool
    {
        // With a prebuilt cache (built at deploy time) an existing cache record is trusted as-is: no filemtime or
        // tracked-resource freshness checks - staleness is the deployer's responsibility
        if (($this->options['features'] & Features::PREBUILT_CACHE) !== 0) {
            return true;
        }

        // The record keeps the size and mtime of the source it was woven from: any difference, also an older
        // mtime restored by a deployment (rsync -t, checkout of an older revision), means the source changed.
        // Weaving depends on the tracked resources too (kernel and aspect files in debug mode)
        $cachedAt = $record['cachedAt'] ?? 0;
        $isStale  = ($record['filemtime'] ?? null) !== filemtime($resource)
            || ($record['filesize'] ?? null) !== filesize($resource)
            || (isset($record['cacheUri']) && !is_string($record['cacheUri']))
            || !$container->isFreshSince(is_int($cachedAt) ? $cachedAt : 0);

        return !$isStale;
    }

    /**
     * Tries to return an information for queried resource
     *
     * @param string|null $resource Name of the file or null to get all records, keyed by {@see self::getRecordKey()}
     *
     * @return array<string, mixed>|null Information or null if no record in the cache
     */
    public function queryCacheState(?string $resource = null): ?array
    {
        $this->loadCacheState();

        if ($resource === null) {
            return $this->cacheState;
        }

        $recordKey = $this->getRecordKey($resource);
        $result    = $this->newCacheState[$recordKey] ?? $this->cacheState[$recordKey] ?? null;

        return is_array($result) ? $result : null;
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

        $this->newCacheState[$this->getRecordKey($resource)] = $metadata;

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

            $this->writeCacheFile(self::CACHE_FILE_NAME, ['version' => self::FORMAT_VERSION, 'files' => $fullCacheMap]);
            $this->writeCacheFile(
                self::INCLUDE_MAP_FILE_NAME,
                ['version' => self::FORMAT_VERSION, 'map' => $classMap, 'skip' => $skippedClasses],
            );

            $this->cacheState     = $fullCacheMap;
            $this->classMap       = $classMap;
            $this->skippedClasses = $skippedClasses;
            $this->newCacheState  = [];
        }
    }

    /**
     * Writes an index file of another cache (the donor index of the z-engine driver) in the metadata format of
     * this manager: a constant PHP return-array next to the transformation metadata, with portable paths
     *
     * @param string               $relativeFileName File name below the cache directory, with a leading slash
     * @param array<string, mixed> $data
     *
     * @throws InvalidConfigurationException When caching is disabled (no cache directory)
     */
    public function writeIndexFile(string $relativeFileName, array $data): void
    {
        if ($this->cacheDir === null) {
            throw new InvalidConfigurationException('Cache index files need the `cacheDir` option to be configured');
        }
        $this->writeCacheFile($relativeFileName, $data);
    }

    /**
     * Writes one cache file as a constant PHP return-array with portable paths
     *
     * The files lie in the cache directory, so every cached file becomes `__DIR__ . '/…'`: a constant expression
     * that keeps the whole array constant for opcache and follows the cache directory when it is moved.
     *
     * @param array<string, mixed> $data
     */
    private function writeCacheFile(string $relativeFileName, array $data): void
    {
        $cachePath = substr(var_export($this->cacheDir, true), 1, -1);
        $cacheData = '<?php return ' . var_export($data, true) . ';';
        $cacheData = str_replace('\'' . $cachePath, '__DIR__ . \'', $cacheData);
        $this->getCacheFileWriter()->write($this->cacheDir . $relativeFileName, $cacheData);
    }

    /**
     * Returns the key of the record of a source: its path relative to the application root
     *
     * A relative key keeps the metadata file constant (no runtime path in it) and valid when the application
     * is moved. Sources outside of the application root are never woven and keep their own path.
     */
    private function getRecordKey(string $resource): string
    {
        return ($this->appDir !== null ? PathResolver::rebase($resource, $this->appDir, '') : null) ?? $resource;
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
