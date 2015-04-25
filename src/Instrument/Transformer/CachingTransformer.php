<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer;

use Go\Aop\Features;
use Go\Core\AspectKernel;
use Go\Instrument\ClassLoading\CachePathManager;

/**
 * Caching transformer that is able to take the transformed source from a cache
 */
class CachingTransformer extends BaseSourceTransformer
{
    /**
     * Root path of application
     *
     * @var string
     */
    protected $rootPath = '';

    /**
     * Cache directory
     *
     * @var string
     */
    protected $cachePath = '';

    /**
     * Mask of permission bits for cache files.
     * By default, permissions are affected by the umask system setting
     *
     * @var integer|null
     */
    protected $cacheFileMode;

    /**
     * @var array|callable|SourceTransformer[]
     */
    protected $transformers = array();

    /**
     * @var CachePathManager|null
     */
    protected $cacheManager;

    /**
     * Class constructor
     *
     * @param AspectKernel $kernel Instance of aspect kernel
     * @param array|callable $transformers Source transformers or callable that should return transformers
     * @param CachePathManager $cacheManager Cache manager
     */
    public function __construct(AspectKernel $kernel, $transformers, CachePathManager $cacheManager)
    {
        parent::__construct($kernel);
        $cacheDir = $this->options['cacheDir'];
        $this->cacheManager = $cacheManager;

        if ($cacheDir) {
            if (!is_dir($cacheDir)) {
                $cacheRootDir = dirname($cacheDir);
                if (!is_writable($cacheRootDir) || !is_dir($cacheRootDir)) {
                    throw new \InvalidArgumentException(
                        "Can not create a directory {$cacheDir} for the cache.
                        Parent directory {$cacheRootDir} is not writable or not exist.");
                }
                mkdir($cacheDir, 0770);
            }
            if (!$this->kernel->hasFeature(Features::PREBUILT_CACHE) && !is_writable($cacheDir)) {
                throw new \InvalidArgumentException("Cache directory {$cacheDir} is not writable");
            }
            $this->cachePath     = $cacheDir;
            $this->cacheFileMode = (int)$this->options['cacheFileMode'];
        }

        $this->rootPath     = $this->options['appDir'];
        $this->transformers = $transformers;
    }

    /**
     * This method may transform the supplied source and return a new replacement for it
     *
     * @param StreamMetaData $metadata Metadata for source
     * @return void|bool Return false if transformation should be stopped
     */
    public function transform(StreamMetaData $metadata)
    {
        // Do not create a cache
        if (!$this->cachePath) {
            return $this->processTransformers($metadata);
        }

        $originalUri  = $metadata->uri;
        $wasProcessed = false;
        $cacheUri     = $this->cacheManager->getCachePathForResource($originalUri);

        $lastModified  = filemtime($originalUri);
        $cacheState    = $this->cacheManager->queryCacheState($originalUri);
        $cacheModified = $cacheState ? $cacheState['filemtime'] : 0;

        if ($cacheModified < $lastModified || !$this->container->isFresh($cacheModified)) {
            $wasProcessed = $this->processTransformers($metadata);
            if ($wasProcessed) {
                $parentCacheDir = dirname($cacheUri);
                if (!is_dir($parentCacheDir)) {
                    mkdir($parentCacheDir, 0770, true);
                }
                file_put_contents($cacheUri, $metadata->source);
                if (!$cacheState && $this->cacheFileMode) {
                    chmod($cacheUri, $this->cacheFileMode);
                }
            }
            $this->cacheManager->setCacheState($originalUri, array(
                'filemtime' => isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time(),
                'processed' => $wasProcessed
            ));

            return $wasProcessed;
        }

        if ($cacheState) {
            $wasProcessed = $cacheState['processed'];
        }
        if ($wasProcessed) {
            $metadata->source = file_get_contents($cacheUri);
        }

        return $wasProcessed;
    }

    /**
     * Iterates over transformers
     *
     * @param StreamMetaData $metadata Metadata for source code
     * @return bool False, if transformation should be stopped
     */
    private function processTransformers(StreamMetaData $metadata)
    {
        if (is_callable($this->transformers)) {
            $delayedTransformers = $this->transformers;
            $this->transformers  = $delayedTransformers();
        }
        foreach ($this->transformers as $transformer) {
            $isTransformed = $transformer->transform($metadata);
            // transformer reported about termination, next transformers will be skipped
            if ($isTransformed === false) {
                return false;
            }
        }

        return true;
    }
}
