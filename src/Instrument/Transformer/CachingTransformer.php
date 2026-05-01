<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer;

use Closure;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\ParserReflection\ReflectionEngine;

use function dirname;

/**
 * Caching transformer that is able to take the transformed source from a cache
 */
class CachingTransformer extends BaseSourceTransformer
{
    /**
     * Mask of permission bits for cache files.
     * By default, permissions are affected by the umask system setting
     */
    protected int $cacheFileMode = 0770;

    /**
     * @var SourceTransformer[]|Closure(): SourceTransformer[]
     */
    protected array|Closure $transformers = [];

    /**
     * Cache manager
     */
    protected CachePathManager $cacheManager;

    /**
     * Class constructor
     *
     * @param SourceTransformer[]|Closure(): SourceTransformer[] $transformers Source transformers or closure that should return transformers
     */
    public function __construct(AspectKernel $kernel, array|Closure $transformers, CachePathManager $cacheManager)
    {
        parent::__construct($kernel);
        $this->cacheManager  = $cacheManager;
        $this->cacheFileMode = $this->options['cacheFileMode'];
        $this->transformers  = $transformers;
    }

    /**
     * This method may transform the supplied source and return a new replacement for it
     */
    public function transform(StreamMetaData $metadata): TransformerResultEnum
    {
        $originalUri      = $metadata->uri;
        $processingResult = TransformerResultEnum::RESULT_ABSTAIN;
        $cacheUri         = $this->cacheManager->getCachePathForResource($originalUri);
        // Guard to disable overwriting of original files or when cache is unavailable
        if ($cacheUri === false || $cacheUri === $originalUri) {
            return TransformerResultEnum::RESULT_ABORTED;
        }

        // Woven (trait) file is stored with the __AopProxied suffix before .php,
        // mirroring the original directory structure under cacheDir.
        $wovenCacheUri = substr($cacheUri, 0, -4) . AspectContainer::AOP_PROXIED_SUFFIX . '.php';

        $lastModified   = filemtime($originalUri);
        $cacheState     = $this->cacheManager->queryCacheState($originalUri);
        $cacheFilemtime = $cacheState !== null ? ($cacheState['filemtime'] ?? 0) : 0;
        $cacheModified  = is_int($cacheFilemtime) ? $cacheFilemtime : 0;

        if ($cacheModified < $lastModified
            || (isset($cacheState['cacheUri']) && !file_exists($wovenCacheUri))
            || !$this->container->hasAnyResourceChangedSince($cacheModified)
        ) {
            $processingResult = $this->processTransformers($metadata);
            if ($processingResult === TransformerResultEnum::RESULT_TRANSFORMED) {
                $parentCacheDir = dirname($wovenCacheUri);
                if (!is_dir($parentCacheDir)) {
                    mkdir($parentCacheDir, $this->cacheFileMode, true);
                }
                file_put_contents($wovenCacheUri, $metadata->source, LOCK_EX);
                // For cache files we don't want executable bits by default
                chmod($wovenCacheUri, $this->cacheFileMode & (~0111));
            }
            $this->cacheManager->setCacheState(
                $originalUri,
                [
                    'filemtime' => $_SERVER['REQUEST_TIME'] ?? time(),
                    'cacheUri'  => ($processingResult === TransformerResultEnum::RESULT_TRANSFORMED) ? $wovenCacheUri : null
                ]
            );

            return $processingResult;
        }

        if ($cacheState) {
            $processingResult = isset($cacheState['cacheUri']) ? TransformerResultEnum::RESULT_TRANSFORMED : TransformerResultEnum::RESULT_ABORTED;
        }
        if ($processingResult === TransformerResultEnum::RESULT_TRANSFORMED) {
            ReflectionEngine::parseFile($wovenCacheUri);
            $metadata->setTokenStreamFromRawTokens(
                ...ReflectionEngine::getParser()->getTokens()
            );
        }

        return $processingResult;
    }

    /**
     * Iterates over transformers
     */
    private function processTransformers(StreamMetaData $metadata): TransformerResultEnum
    {
        $overallResult = TransformerResultEnum::RESULT_ABSTAIN;
        if ($this->transformers instanceof Closure) {
            $delayedTransformers = $this->transformers;
            $this->transformers  = $delayedTransformers();
        }
        foreach ($this->transformers as $transformer) {
            $transformationResult = $transformer->transform($metadata);
            if ($overallResult === TransformerResultEnum::RESULT_ABSTAIN && $transformationResult === TransformerResultEnum::RESULT_TRANSFORMED) {
                $overallResult = TransformerResultEnum::RESULT_TRANSFORMED;
            }
            // transformer reported about termination, next transformers will be skipped
            if ($transformationResult === TransformerResultEnum::RESULT_ABORTED) {
                $overallResult = TransformerResultEnum::RESULT_ABORTED;
                break;
            }
        }

        return $overallResult;
    }
}
