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

use Closure;
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
     * Name of the file with the minimal runtime include map: woven classes, untransformed classes and known files
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
    public const int FORMAT_VERSION = 4;

    /**
     * Length of the head of the include map that holds its stamp: `<?php return array (`, the version and the stamp
     */
    private const int STAMP_HEAD_LENGTH = 128;

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
     * Minimal runtime map of every source known to the cache, keyed by {@see self::getRecordKey()}, to its cached
     * file, or to null when the source was not transformed
     *
     * Serves the includes rewritten by FilterInjectorTransformer without the full metadata.
     *
     * @var array<string, string|null>
     */
    private array $fileMap = [];

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
     * @var array<string, array<string, mixed>>
     */
    protected array $newCacheState = [];

    /**
     * Stamp of the metadata as this manager loaded or last wrote it, see {@see self::flushCacheState()}
     *
     * The hash of the transformation metadata, written into the include map: the include map read by every request
     * carries it, so the identity of the loaded state costs no stat call. Null when there was no include map, false
     * when its stamp is unknown (an outdated include map, written without one): the files are then taken as
     * rewritten by another writer.
     */
    private string|false|null $includeMapStamp = null;

    /**
     * Whether a cache is being built, {@see self::withCacheDir()}: the build re-checks the freshness of every record,
     * also of a prebuilt cache, which requests trust as it is
     */
    public private(set) bool $isBuildingCache = false;

    public function __construct(
        protected readonly AspectKernel $kernel,
    ) {
        $options        = $kernel->getOptions();
        $this->options  = $options;
        $this->appDir   = $options['appDir'];
        $this->cacheDir = $options['cacheDir'];
        $this->fileMode = $options['cacheFileMode'];

        $this->loadCacheDirectory();

        // Flush pending cache records while the runtime environment is still fully
        // intact: object destruction order during shutdown is unspecified, so relying
        // on __destruct() alone can run the write after collaborators are torn down
        register_shutdown_function($this->flushSilently(...));
    }

    /**
     * Prepares the cache directory and reads its include map: the woven class map, the skipped classes and the
     * known files, which serve every request without the full metadata
     */
    private function loadCacheDirectory(): void
    {
        if ($this->cacheDir === null || $this->cacheDir === '') {
            return;
        }
        // Included without any stat call before: opcache serves a warm include map from memory, the include of a
        // missing one fails, quietly. The include map carries the stamp of the metadata it was written with
        $includeData           = @include $this->cacheDir . self::INCLUDE_MAP_FILE_NAME;
        $hasIncludeMap         = $includeData !== false;
        $this->includeMapStamp = null;
        // With a prebuilt cache the directory is guaranteed to exist (built at deploy
        // time), so all directory/writability stat checks are skipped - this also
        // covers read-only file systems (GAE, phar, etc). An existing include map
        // proves the directory too: a warm cache costs no stat calls, and a cache
        // that became read-only fails on the next write only, not on every request
        if (!$hasIncludeMap && !$this->kernel->hasFeature(Features::PREBUILT_CACHE)) {
            $this->ensureCacheDirectoryIsWritable($this->cacheDir);
        }

        if ($hasIncludeMap) {
            if (!$this->isCurrentFormat($includeData)) {
                $this->rejectOutdatedFormat();
                // Outdated format: everything re-weaves once and both files are rewritten
                $this->cacheStateLoaded = true;
                $this->includeMapStamp  = false;
            } else {
                // A file of the current format is written by flushCacheState() only, so its arrays are used
                // as they are: checking every entry would cost a loop over the whole map on every request
                /** @var array{stamp?: string, map: array<class-string, string>, skip: array<class-string, true>, files: array<string, string|null>} $includeData */
                $this->includeMapStamp = $includeData['stamp'] ?? false;
                $this->classMap        = $includeData['map'];
                $this->skippedClasses  = $includeData['skip'];
                $this->fileMap         = $includeData['files'];
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

    /**
     * Creates the cache directory with its missing parents when it is missing and checks that it can be written
     */
    private function ensureCacheDirectoryIsWritable(string $cacheDir): void
    {
        if (!is_dir($cacheDir)) {
            // mkdir() is recursive: only the nearest existing ancestor has to be a writable directory
            $existingAncestor = dirname($cacheDir);
            while (!file_exists($existingAncestor) && dirname($existingAncestor) !== $existingAncestor) {
                $existingAncestor = dirname($existingAncestor);
            }
            if (!is_dir($existingAncestor) || !is_writable($existingAncestor)) {
                throw InvalidConfigurationException::cacheDirectoryNotCreatable($cacheDir, $existingAncestor);
            }
            mkdir($cacheDir, CacheFileWriter::directoryModeFor($this->fileMode), true);
        }
        if (!is_writable($cacheDir)) {
            throw InvalidConfigurationException::cacheDirectoryNotWritable($cacheDir);
        }
    }

    /**
     * Runs an operation with the cache in another directory, built from scratch.
     *
     * The pending records of the current directory are flushed and its state is kept aside; the operation
     * works on the other directory as if the kernel had been configured with it (its include map is read
     * when it exists, the directory is created otherwise), and the current directory is restored
     * afterwards, with its state untouched. Used by the cache warmer to build the cache of a deployment
     * next to the running one: the kernel keeps serving this process from its own directory.
     *
     * With the current directory the operation runs in place: the directory is created when it is missing
     * and the records of the operation are flushed when it ends.
     *
     * Either way the operation builds a cache: every record is checked for freshness like without a prebuilt
     * cache, and metadata of another format is rebuilt, so that a build over an existing directory refreshes what
     * changed ({@see self::$isBuildingCache}).
     *
     * @template T
     *
     * @param Closure(): T $operation
     *
     * @return T
     */
    public function withCacheDir(string $cacheDir, Closure $operation): mixed
    {
        $wasBuildingCache      = $this->isBuildingCache;
        $this->isBuildingCache = true;
        try {
            return $this->buildCacheDir(self::resolvePath($cacheDir), $operation);
        } finally {
            $this->isBuildingCache = $wasBuildingCache;
        }
    }

    /**
     * @template T
     *
     * @param Closure(): T $operation
     *
     * @return T
     */
    private function buildCacheDir(string $cacheDir, Closure $operation): mixed
    {
        if ($this->cacheDir !== null && $cacheDir === self::resolvePath($this->cacheDir)) {
            // The directory of a build is written in any case, also the own one of a kernel that never
            // created it: with PREBUILT_CACHE the kernel expects the directory to be built at deploy time
            $this->ensureCacheDirectoryIsWritable($this->cacheDir);
            try {
                return $operation();
            } finally {
                $this->flushCacheState();
            }
        }

        $this->flushCacheState();
        $state = [
            $this->cacheDir,
            $this->cacheState,
            $this->cacheStateLoaded,
            $this->classMap,
            $this->skippedClasses,
            $this->fileMap,
            $this->pendingClasses,
            $this->newCacheState,
            $this->includeMapStamp,
        ];

        $this->resetCacheState();
        $this->cacheDir = $cacheDir;
        try {
            // The other directory is written in any case, also by a kernel that never writes its own one
            $this->ensureCacheDirectoryIsWritable($cacheDir);
            $this->loadCacheDirectory();

            return $operation();
        } finally {
            $this->flushCacheState();
            [
                $this->cacheDir,
                $this->cacheState,
                $this->cacheStateLoaded,
                $this->classMap,
                $this->skippedClasses,
                $this->fileMap,
                $this->pendingClasses,
                $this->newCacheState,
                $this->includeMapStamp,
            ] = $state;
        }
    }

    /**
     * Resolves a cache directory path the way the kernel options are resolved, so that the same directory
     * compares equal whether it exists yet or not
     */
    private static function resolvePath(string $path): string
    {
        $resolvedPath = PathResolver::realpath($path);

        return is_string($resolvedPath) ? $resolvedPath : $path;
    }

    /**
     * Reads the stamp of the include map file on disk: null when there is none, false when it carries no stamp
     *
     * Read from the head of the file, not included: opcache may still hold the version this process loaded. Only a
     * flush reads it, a request that writes nothing never does.
     */
    private function readIncludeMapStamp(): string|false|null
    {
        $head = @file_get_contents($this->cacheDir . self::INCLUDE_MAP_FILE_NAME, false, null, 0, self::STAMP_HEAD_LENGTH);
        if ($head === false) {
            return null;
        }

        return preg_match("/^  'stamp' => '([0-9a-f]+)',$/m", $head, $matches) === 1 ? $matches[1] : false;
    }

    /**
     * Picks the records of another writer up, when the cache files were rewritten since this manager loaded them
     *
     * A warm-up into this directory, a concurrent request, or a deployment that swapped a warmed-up directory in
     * (Symfony's `cache:clear` builds the new cache next to the running one and renames it) wrote the files after
     * this manager loaded them, with a state this manager knows nothing about: possibly woven with other aspects.
     * Their records win, the pending records of this manager survive only for the sources they do not know,
     * so that a flush at the end of the process never undoes what was built meanwhile.
     */
    private function adoptCacheFilesOfAnotherWriter(): void
    {
        $stampOnDisk = $this->readIncludeMapStamp();
        if ($this->includeMapStamp !== false && $this->includeMapStamp === $stampOnDisk) {
            return;
        }
        $this->cacheState       = [];
        $this->cacheStateLoaded = false;
        $this->loadCacheState();
        $this->newCacheState   = array_diff_key($this->newCacheState, $this->cacheState);
        $this->includeMapStamp = $stampOnDisk;
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
     *
     * A build replaces it.
     */
    private function rejectOutdatedFormat(): void
    {
        if (!$this->isBuildingCache && $this->kernel->hasFeature(Features::PREBUILT_CACHE)) {
            throw WeavingException::prebuiltCacheVersionMismatch((string) $this->cacheDir);
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
     * Returns the file to include natively for a source known to the include map
     *
     * The cached file of a woven source, the source itself for a source recorded as untransformed, or null for a
     * source unknown to the cache. Like a class of the class map, a known source is trusted without any freshness
     * check, and the full metadata is never loaded.
     */
    public function findIncludeFile(string $resource): ?string
    {
        $recordKey = $this->getRecordKey($resource);
        if (isset($this->fileMap[$recordKey])) {
            return $this->fileMap[$recordKey];
        }

        return array_key_exists($recordKey, $this->fileMap) ? $resource : null;
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

        // With a prebuilt cache (built at deploy time) an existing cache record is trusted as-is: no filemtime or
        // tracked-resource freshness checks - staleness is the deployer's responsibility. The build of the cache
        // (cache:warmup:aop, also over an existing directory) checks every record, to rebuild what changed
        if ($cacheState === null || (($this->options['features'] & Features::PREBUILT_CACHE) !== 0 && !$this->isBuildingCache)) {
            return $cacheState;
        }

        // The record keeps the size and mtime of the source it was woven from: any difference, also an older
        // mtime restored by a deployment (rsync -t, checkout of an older revision), means the source changed.
        // Weaving depends on the tracked resources too (kernel and aspect files in debug mode)
        $cachedAt = $cacheState['cachedAt'] ?? 0;
        $isStale  = ($cacheState['filemtime'] ?? null) !== filemtime($resource)
            || ($cacheState['filesize'] ?? null) !== filesize($resource)
            || (isset($cacheState['cacheUri']) && !is_string($cacheState['cacheUri']))
            || !$container->isFreshSince(is_int($cachedAt) ? $cachedAt : 0);

        return $isStale ? null : $cacheState;
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

        $this->storeRecord($this->getRecordKey($resource), $metadata);
    }

    /**
     * Returns the records put since the last flush and forgets them
     *
     * A cache warmup worker hands its records over to the coordinating process, which persists the records of all
     * workers at once: several processes flushing their own view of the metadata would overwrite each other.
     *
     * @return array<string, array<string, mixed>> Records keyed by {@see self::getRecordKey()}
     */
    public function takeNewCacheState(): array
    {
        $records             = $this->newCacheState;
        $this->newCacheState = [];

        return $records;
    }

    /**
     * Puts records produced by another process (a cache warmup worker) in the cache
     *
     * Like setCacheState(), the records are persisted on the next flush.
     *
     * @param array<string, array<string, mixed>> $records Records keyed by {@see self::getRecordKey()}, as returned by
     *                                                    {@see self::takeNewCacheState()}
     */
    public function mergeCacheState(array $records): void
    {
        foreach ($records as $recordKey => $metadata) {
            $this->storeRecord($recordKey, $metadata);
        }
    }

    /**
     * Stores a record to be flushed and keeps the in-memory runtime maps coherent within this request
     *
     * @param array<string, mixed> $metadata
     */
    private function storeRecord(string $recordKey, array $metadata): void
    {
        $this->newCacheState[$recordKey] = $metadata;

        $classNames = is_array($metadata['classes'] ?? null) ? $metadata['classes'] : [];
        $cacheUri   = $metadata['cacheUri'] ?? null;
        $this->fileMap[$recordKey] = is_string($cacheUri) ? $cacheUri : null;
        foreach ($classNames as $className) {
            if (!is_string($className)) {
                continue;
            }
            /** @var class-string $className */
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
            if (!$force) {
                $this->adoptCacheFilesOfAnotherWriter();
                if ($this->newCacheState === []) {
                    return;
                }
            }
            // The full metadata must be loaded before merging, otherwise entries that were
            // never queried during this request would be dropped from the written file
            $this->loadCacheState();
            $fullCacheMap = $this->newCacheState + $this->cacheState;

            $classMap       = [];
            $skippedClasses = [];
            $fileMap        = [];
            foreach ($fullCacheMap as $recordKey => $metadata) {
                if (!is_array($metadata)) {
                    continue;
                }
                $cacheUri   = $metadata['cacheUri'] ?? null;
                $fileMap[$recordKey] = is_string($cacheUri) ? $cacheUri : null;
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

            $metadata = $this->writeCacheFile(self::CACHE_FILE_NAME, ['version' => self::FORMAT_VERSION, 'files' => $fullCacheMap]);
            // The stamp identifies the written metadata: other records (also the weaving times) give another one
            $stamp = hash('xxh128', $metadata);
            $this->writeCacheFile(
                self::INCLUDE_MAP_FILE_NAME,
                ['version' => self::FORMAT_VERSION, 'stamp' => $stamp, 'map' => $classMap, 'skip' => $skippedClasses, 'files' => $fileMap],
            );

            $this->cacheState      = $fullCacheMap;
            $this->classMap        = $classMap;
            $this->skippedClasses  = $skippedClasses;
            $this->fileMap         = $fileMap;
            $this->newCacheState   = [];
            $this->includeMapStamp = $stamp;
        }
    }

    /**
     * Writes one cache file as a constant PHP return-array with portable paths
     *
     * The files lie in the cache directory, so every cached file becomes `__DIR__ . '/…'`: a constant expression
     * that keeps the whole array constant for opcache and follows the cache directory when it is moved.
     *
     * @param array<string, mixed> $data
     *
     * @return string Written content
     */
    private function writeCacheFile(string $relativeFileName, array $data): string
    {
        $cachePath = substr(var_export($this->cacheDir, true), 1, -1);
        $cacheData = '<?php return ' . var_export($data, true) . ';';
        $cacheData = str_replace('\'' . $cachePath, '__DIR__ . \'', $cacheData);
        $this->getCacheFileWriter()->write($this->cacheDir . $relativeFileName, $cacheData);

        return $cacheData;
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
        $this->resetCacheState();
        $this->cacheStateLoaded = true;

        $this->flushCacheState(true);
    }

    /**
     * Forgets everything known about the cache directory, in memory only
     */
    private function resetCacheState(): void
    {
        $this->cacheState       = [];
        $this->cacheStateLoaded = false;
        $this->classMap         = [];
        $this->skippedClasses   = [];
        $this->fileMap          = [];
        $this->pendingClasses   = [];
        $this->newCacheState    = [];
        $this->includeMapStamp  = null;
    }
}
