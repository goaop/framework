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
use Symfony\Component\Filesystem\Filesystem;

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
     * Symfony Filesystem instance for all file operations
     */
    protected static Filesystem $filesystem;

    /**
     * Register current loader as stream filter in PHP
     *
     * @throws RuntimeException If registration was failed
     */
    public static function register(
        AspectContainer $container,
        CachePathManager $cacheManager,
        Filesystem $filesystem,
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
        self::$filesystem    = $filesystem;
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
            if (self::cacheIsFresh($metadata)) {
                $content = self::getCachedCode($metadata);
            } else {
                $content = self::transformCode($metadata);
            }

            $bucket = stream_bucket_new($this->stream, $content);
            stream_bucket_append($out, $bucket);

            return PSFS_PASS_ON;
        }

        return PSFS_FEED_ME;
    }

    /**
     * Checks whether a fresh cached version exists for the given metadata URI.
     */
    private static function cacheIsFresh(StreamMetaData $metadata): bool
    {
        $originalUri = $metadata->uri;
        $cacheUri    = self::$cacheManager->getCachePathForResource($originalUri);

        if ($cacheUri === false) {
            return false;
        }
        if ($cacheUri === $originalUri) {
            throw new RuntimeException("Cache path resolves to the original file: {$originalUri}");
        }

        $lastModified   = filemtime($originalUri);
        $cacheState     = self::$cacheManager->queryCacheState($originalUri);
        $cacheFilemtime = $cacheState !== null ? ($cacheState['filemtime'] ?? 0) : 0;
        $cacheModified  = is_int($cacheFilemtime) ? $cacheFilemtime : 0;

        if ($cacheModified < $lastModified
            || (isset($cacheState['cacheUri']) && $cacheState['cacheUri'] !== $cacheUri)
            || !self::$container->hasAnyResourceChangedSince($cacheModified)
        ) {
            return false;
        }

        return true;
    }

    /**
     * Returns cached source code for the given metadata, avoiding re-tokenization.
     */
    private static function getCachedCode(StreamMetaData $metadata): string
    {
        $cacheState = self::$cacheManager->queryCacheState($metadata->uri);
        if ($cacheState && isset($cacheState['cacheUri']) && is_string($cacheState['cacheUri'])) {
            if (self::$filesystem->exists($cacheState['cacheUri'])) {
                return self::$filesystem->readFile($cacheState['cacheUri']);
            }
        }

        return $metadata->source;
    }

    /**
     * Runs the transformer chain, writes the result to cache, and returns the source.
     */
    public static function transformCode(StreamMetaData $metadata): string
    {
        $originalUri = $metadata->uri;
        $cacheUri    = self::$cacheManager->getCachePathForResource($originalUri);

        if ($cacheUri === false) {
            return $metadata->source;
        }
        if ($cacheUri === $originalUri) {
            throw new RuntimeException("Cache path resolves to the original file: {$originalUri}");
        }

        $processingResult = self::processTransformers($metadata);
        if ($processingResult === TransformerResultEnum::RESULT_TRANSFORMED) {
            self::$filesystem->mkdir(dirname($cacheUri), self::$cacheFileMode);
            self::$filesystem->dumpFile($cacheUri, $metadata->source);
            self::$filesystem->chmod($cacheUri, self::$cacheFileMode & (~0111));
        }
        self::$cacheManager->setCacheState(
            $originalUri,
            [
                'filemtime' => $_SERVER['REQUEST_TIME'] ?? time(),
                'cacheUri'  => ($processingResult === TransformerResultEnum::RESULT_TRANSFORMED) ? $cacheUri : null,
            ]
        );

        return $metadata->source;
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
