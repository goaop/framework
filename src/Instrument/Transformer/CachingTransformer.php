<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer;

use Go\Core\AspectKernel;
use Go\Instrument\ClassLoading\CachePathManager;

/**
 * Caching transformer that is able to take the transformed source from a cache
 */
class CachingTransformer extends BaseSourceTransformer
{
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
    protected $transformers = [];

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
        $this->cacheManager  = $cacheManager;
        $this->cacheFileMode = $this->options['cacheFileMode'];
        $this->transformers  = $transformers;
    }

    /**
     * This method may transform the supplied source and return a new replacement for it
     *
     * @param StreamMetaData $metadata Metadata for source
     * @return string See RESULT_XXX constants in the interface
     */
    public function transform(StreamMetaData $metadata)
    {
        $originalUri      = $metadata->uri;
        $processingResult = self::RESULT_ABSTAIN;
        $cacheUri         = $this->cacheManager->getCachePathForResource($originalUri);
        // Guard to disable overwriting of original files
        if ($cacheUri === $originalUri) {
            return self::RESULT_ABORTED;
        }

        $lastModified  = filemtime($originalUri);
        $cacheState    = $this->cacheManager->queryCacheState($originalUri);
        $cacheModified = $cacheState ? $cacheState['filemtime'] : 0;

        if ($cacheModified < $lastModified
            || (isset($cacheState['cacheUri']) && $cacheState['cacheUri'] !== $cacheUri)
            || !$this->container->isFresh($cacheModified)
        ) {
            $processingResult = $this->processTransformers($metadata);
            if ($processingResult === self::RESULT_TRANSFORMED) {
                $parentCacheDir = dirname($cacheUri);
                if (!is_dir($parentCacheDir)) {
                    mkdir($parentCacheDir, $this->cacheFileMode, true);
                }
                file_put_contents($cacheUri, $metadata->source, LOCK_EX);
                // For cache files we don't want executable bits by default
                chmod($cacheUri, $this->cacheFileMode & (~0111));
            }
            $this->cacheManager->setCacheState($originalUri, [
                'filemtime' => isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time(),
                'cacheUri'  => ($processingResult === self::RESULT_TRANSFORMED) ? $cacheUri : null
            ]);

            return $processingResult;
        }

        if ($cacheState) {
            $processingResult = isset($cacheState['cacheUri']) ? self::RESULT_TRANSFORMED : self::RESULT_ABORTED;
        }
        if ($processingResult === self::RESULT_TRANSFORMED) {
            // Just replace all tokens in the stream
            $metadata->tokenStream = token_get_all(file_get_contents($cacheUri));
        }

        return $processingResult;
    }

    /**
     * Iterates over transformers
     *
     * @param StreamMetaData $metadata Metadata for source code
     * @return string See RESULT_XXX constants in the interface
     */
    private function processTransformers(StreamMetaData $metadata)
    {
        $overallResult = self::RESULT_ABSTAIN;
        if (is_callable($this->transformers)) {
            $delayedTransformers = $this->transformers;
            $this->transformers  = $delayedTransformers();
        }
        foreach ($this->transformers as $transformer) {
            $transformationResult = $transformer->transform($metadata);
            if ($overallResult === self::RESULT_ABSTAIN && $transformationResult === self::RESULT_TRANSFORMED) {
                $overallResult = self::RESULT_TRANSFORMED;
            }
            // transformer reported about termination, next transformers will be skipped
            if ($transformationResult === self::RESULT_ABORTED) {
                $overallResult = self::RESULT_ABORTED;
                break;
            }
        }

        return $overallResult;
    }
}
