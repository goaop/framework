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
    public function setCacheDir($cacheDir)
    {
        $this->cacheDir = $cacheDir;
    }

    /**
     * @param string $resource
     * @return bool|string
     */
    public function getCachePathForResource($resource)
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
    public function queryCacheState($resource = null)
    {
        if (!$resource) {
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
     * @param string $resource Name of the file
     * @param array $metadata Miscellaneous information about resource
     */
    public function setCacheState($resource, array $metadata)
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
    public function flushCacheState()
    {
        if (!empty($this->newCacheState) && is_writable($this->cacheDir)) {
            $fullCacheMap = $this->newCacheState + $this->cacheState;
            $cachePath    = substr(var_export($this->cacheDir, true), 1, -1);
            $rootPath     = substr(var_export($this->appDir, true), 1, -1);
            $cacheData    = '<?php return ' . var_export($fullCacheMap, true) . ';';
            $cacheData    = strtr($cacheData, array(
                '\'' . $cachePath => 'AOP_CACHE_DIR . \'',
                '\'' . $rootPath  => 'AOP_ROOT_DIR . \''
            ));
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
}
