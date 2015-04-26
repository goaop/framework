<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\ClassLoading;
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
    protected $options = array();

    /**
     * @var \Go\Core\AspectKernel
     */
    protected $kernel;

    /**
     * @var string|null
     */
    protected $cacheDir;

    /**
     * @var string|null
     */
    protected $appDir;

    /**
     * Cached metadata for transformation state for the concrete file
     *
     * @var array
     */
    protected $cacheState = array();

    /**
     * New metadata items, that was not present in $cacheState
     *
     * @var array
     */
    protected $newCacheState = array();

    public function __construct (AspectKernel $kernel)
    {
        $this->kernel   = $kernel;
        $this->options  = $kernel->getOptions();
        $this->cacheDir = $this->options['cacheDir'];
        $this->appDir   = $this->options['appDir'];

        if ($this->cacheDir && file_exists($this->cacheDir. self::CACHE_FILE_NAME)) {
            $this->cacheState = include $this->cacheDir . self::CACHE_FILE_NAME;
        }
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
    public function queryCacheState($resource=null)
    {
        if (!$resource) {
            return $this->cacheState;
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
        if ($this->newCacheState) {
            $fullCacheMap = $this->newCacheState + $this->cacheState;
            $cacheData    = '<?php return ' . var_export($fullCacheMap, true) . ';';
            file_put_contents($this->cacheDir . self::CACHE_FILE_NAME, $cacheData);
        }
    }
}
