<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\ClassLoading;

use Go\Core\AspectContainer;
use Go\Instrument\Transformer\SourceTransformer;
use Go\Instrument\Transformer\StreamMetaData;
use Go\Instrument\Transformer\TransformerResultEnum;
use php_user_filter as PhpStreamFilter;
use RuntimeException;

use function strlen;

/**
 * Php class loader filter for processing php code
 *
 * @phpstan-property resource $stream Inherited from php_user_filter; typed here for static analysis
 */
class SourceTransformingLoader extends PhpStreamFilter
{
    /**
     * Php filter definition
     */
    public const PHP_FILTER_READ = 'php://filter/read=';

    /**
     * Default PHP filter name for registration
     */
    public const FILTER_IDENTIFIER = 'go.source.transforming.loader';

    /**
     * String buffer
     */
    protected string $data = '';

    /**
     * Identifier of filter
     */
    protected static string $filterId;

    /**
     * Aspect container instance for fetching transformers and checking resource freshness
     */
    protected static AspectContainer $container;

    /**
     * Cache path manager for resolving and querying cache state
     */
    protected static CachePathManager $cacheManager;

    /**
     * Mask of permission bits for cache files
     */
    protected static int $cacheFileMode = 0770;

    /**
     * Register current loader as stream filter in PHP
     *
     * @throws RuntimeException If registration was failed
     */
    public static function register(
        AspectContainer $container,
        CachePathManager $cacheManager,
        int $cacheFileMode,
        string $filterId = self::FILTER_IDENTIFIER,
    ): void {
        if (!empty(self::$filterId)) {
            throw new RuntimeException('Stream filter already registered');
        }

        $result = stream_filter_register($filterId, self::class);
        if ($result === false) {
            throw new RuntimeException('Stream filter was not registered');
        }
        self::$filterId      = $filterId;
        self::$container     = $container;
        self::$cacheManager  = $cacheManager;
        self::$cacheFileMode = $cacheFileMode;
    }

    /**
     * Returns the name of registered filter
     *
     * @throws RuntimeException if filter was not registered
     */
    public static function getId(): string
    {
        if (empty(self::$filterId)) {
            throw new RuntimeException('Stream filter was not registered');
        }

        return self::$filterId;
    }

    /**
     * {@inheritdoc}
     */
    public function filter($in, $out, &$consumed, $closing): int
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $this->data .= $bucket->data;
        }

        if ($closing || feof($this->stream)) {
            $consumed = strlen($this->data);

            // $this->stream contains pointer to the source
            $metadata = new StreamMetaData($this->stream, $this->data);
            $result   = self::transformCode($metadata);

            $bucket = stream_bucket_new($this->stream, $result ?? $metadata->source);
            stream_bucket_append($out, $bucket);

            return PSFS_PASS_ON;
        }

        return PSFS_FEED_ME;
    }

    /**
     * Transforms source code by passing it through all registered transformers, with caching.
     *
     * On cache hit, returns the cached source as a string (skipping tokenization entirely).
     * On cache miss, runs the transformer chain, writes the result to cache, and returns null
     * (the caller should read $metadata->source which reflects token-level changes).
     */
    public static function transformCode(StreamMetaData $metadata): ?string
    {
        $originalUri = $metadata->uri;
        $cacheUri    = self::$cacheManager->getCachePathForResource($originalUri);

        // Guard to disable overwriting of original files or when cache is unavailable
        if ($cacheUri === false || $cacheUri === $originalUri) {
            return null;
        }

        $lastModified   = filemtime($originalUri);
        $cacheState     = self::$cacheManager->queryCacheState($originalUri);
        $cacheFilemtime = $cacheState !== null ? ($cacheState['filemtime'] ?? 0) : 0;
        $cacheModified  = is_int($cacheFilemtime) ? $cacheFilemtime : 0;

        if ($cacheModified < $lastModified
            || (isset($cacheState['cacheUri']) && $cacheState['cacheUri'] !== $cacheUri)
            || !self::$container->hasAnyResourceChangedSince($cacheModified)
        ) {
            // Cache miss — run all transformers
            $processingResult = self::processTransformers($metadata);
            if ($processingResult === TransformerResultEnum::RESULT_TRANSFORMED) {
                $parentCacheDir = dirname($cacheUri);
                if (!is_dir($parentCacheDir)) {
                    mkdir($parentCacheDir, self::$cacheFileMode, true);
                }
                file_put_contents($cacheUri, $metadata->source, LOCK_EX);
                // For cache files we don't want executable bits by default
                chmod($cacheUri, self::$cacheFileMode & (~0111));
            }
            self::$cacheManager->setCacheState(
                $originalUri,
                [
                    'filemtime' => $_SERVER['REQUEST_TIME'] ?? time(),
                    'cacheUri'  => ($processingResult === TransformerResultEnum::RESULT_TRANSFORMED) ? $cacheUri : null,
                ]
            );

            return null;
        }

        // Cache hit — return cached source directly as string, avoiding re-tokenization
        if ($cacheState && isset($cacheState['cacheUri']) && is_string($cacheState['cacheUri'])) {
            $cachedSource = file_get_contents($cacheState['cacheUri']);
            if (is_string($cachedSource)) {
                return $cachedSource;
            }
        }

        return null;
    }

    /**
     * Iterates over transformers fetched from the container
     */
    private static function processTransformers(StreamMetaData $metadata): TransformerResultEnum
    {
        $overallResult = TransformerResultEnum::RESULT_ABSTAIN;
        $transformers = self::$container->getServicesByInterface(SourceTransformer::class);

        foreach ($transformers as $transformer) {
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
