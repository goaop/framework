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

namespace Go\Instrument\ClassLoading;
use Go\Aop\Features;
use Go\Core\AspectKernel;

/**
 * Class that manages real-code to cached-code paths mapping.
 * Can be extended to get a more sophisticated real-to-cached code mapping
 */
class CachePathManager
{
    /**
     * Name of the file with cache paths
     */
    const CACHE_FILE_NAME = '/_transformation.cache';

    /**
     * Name of the file with class maps
     */
    const CACHE_MAP_FILE_NAME = '/_classmap.cache';

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var \Go\Core\AspectKernel
     */
    protected $kernel;

    /**
     * @var string|null
     */
    protected $cacheDir;

    /**
     * File mode
     *
     * @var integer
     */
    protected $fileMode;

    /**
     * @var string|null
     */
    protected $appDir;

    /**
     * Cached metadata for transformation state for the concrete file
     *
     * @var array
     */
    protected $cacheState = [];

    /**
     * New metadata items, that was not present in $cacheState
     *
     * @var array
     */
    protected $newCacheState = [];

    /**
     * Map of classes to the filenames
     *
     * @var array
     */
    protected $classMap = [];

    /**
     * New classmap items, that was not present in $classMap
     *
     * @var array
     */
    protected $newClassMap = [];

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
                    throw new \InvalidArgumentException(
                        "Can not create a directory {$this->cacheDir} for the cache.
                        Parent directory {$cacheRootDir} is not writable or not exist.");
                }
                mkdir($this->cacheDir, $this->fileMode, true);
            }
            if (!$this->kernel->hasFeature(Features::PREBUILT_CACHE) && !is_writable($this->cacheDir)) {
                throw new \InvalidArgumentException("Cache directory {$this->cacheDir} is not writable");
            }

            if (file_exists($this->cacheDir . self::CACHE_FILE_NAME)) {
                $this->cacheState = include $this->cacheDir . self::CACHE_FILE_NAME;
            }
            if (file_exists($this->cacheDir . self::CACHE_MAP_FILE_NAME)) {
                $this->classMap = include $this->cacheDir . self::CACHE_MAP_FILE_NAME;
            }
        }
    }

    /**
     * Returns current cache directory for aspects, can be bull
     *
     * @return null|string
     */
    public function getCacheDir()
    {
        return $this->cacheDir;
    }

    /**
     * Configures a new cache directory for aspects
     *
     * @param string $cacheDir New cache directory
     */
    public function setCacheDir(string $cacheDir)
    {
        $this->cacheDir = $cacheDir;
    }

    /**
     * @param string $resource
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
    public function &queryCacheState(string $resource = null)
    {
        $result = [];
        if ($resource === null) {
            $result = &$this->cacheState;
        }

        if (isset($this->cacheState[$resource])) {
            $result = &$this->cacheState[$resource];
        }

        return $result;
    }

    /**
     * Returns an information about class mapping
     */
    public function &queryClassMap(): array
    {
        $result = &$this->classMap;

        return $result;
    }

    /**
     * Put a record about some resource in the cache
     *
     * This data will be persisted during object destruction
     *
     * @param string $resource Name of the file
     * @param array $metadata Miscellaneous information about resource
     */
    public function setCacheState(string $resource, array $metadata)
    {
        $this->newCacheState[$resource] = $metadata;
        $this->cacheState[$resource]    = $metadata;
    }

    /**
     * Put a mapping for the class
     *
     * This data will be persisted during object destruction
     *
     * @param string $class         Name of the class
     * @param array  $classFileName Miscellaneous information about resource
     */
    public function addClassMap(string $class, $classFileName)
    {
        $this->newClassMap[$class] = $classFileName;
        $this->classMap[$class]    = $classFileName;
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
     *
     * @var bool $force Should be flushed regardless of its state.
     */
    public function flushCacheState($force = false)
    {

        if ((!empty($this->newCacheState) && is_writable($this->cacheDir)) || $force) {
            $fullCacheMap = $this->newCacheState + $this->cacheState;
            $cachePath    = substr(var_export($this->cacheDir, true), 1, -1);
            $rootPath     = substr(var_export($this->appDir, true), 1, -1);
            $cacheData    = '<?php return ' . var_export($fullCacheMap, true) . ';';
            $cacheData    = strtr($cacheData, [
                '\'' . $cachePath => 'AOP_CACHE_DIR . \'',
                '\'' . $rootPath  => 'AOP_ROOT_DIR . \''
            ]);
            $fullCacheFileName = $this->cacheDir . self::CACHE_FILE_NAME;
            file_put_contents($fullCacheFileName, $cacheData, LOCK_EX);
            // For cache files we don't want executable bits by default
            chmod($fullCacheFileName, $this->fileMode & (~0111));

            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($fullCacheFileName, true);
            }
            $this->newCacheState = [];
        }
        if (!empty($this->newClassMap) && is_writable($this->cacheDir)) {
            $cachePath = substr(var_export($this->cacheDir, true), 1, -1);
            $rootPath  = substr(var_export($this->appDir, true), 1, -1);
            $cacheData = '<?php return ' . var_export($this->classMap, true) . ';';
            $cacheData = strtr($cacheData, array(
                '\'' . $cachePath => 'AOP_CACHE_DIR . \'',
                '\'' . $rootPath  => 'AOP_ROOT_DIR . \''
            ));
            $fullCacheFileName = $this->cacheDir . self::CACHE_MAP_FILE_NAME;
            file_put_contents($fullCacheFileName, $cacheData, LOCK_EX);
            // For cache files we don't want executable bits by default
            chmod($fullCacheFileName, $this->fileMode & (~0111));

            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($fullCacheFileName, true);
            }
            $this->newClassMap = [];
        }
    }

    /**
     * Clear the cache state.
     */
    public function clearCacheState()
    {
        $this->cacheState       = [];
        $this->newCacheState    = [];

        $this->flushCacheState(true);
    }
}
