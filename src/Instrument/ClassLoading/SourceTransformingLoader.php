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

use Go\Aop\Exception\WeavingException;
use Go\Aop\Features;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Instrument\PathResolver;
use Go\Instrument\Transformer\SourceTransformer;
use Go\Instrument\Transformer\StreamMetaData;
use Go\Instrument\Transformer\TransformerResult;
use php_user_filter as PhpStreamFilter;
use RuntimeException;

use function is_string;
use function strlen;

/**
 * Php class loader filter for processing php code
 *
 * Caching lives right here, in the core of the framework: when a usable cache record
 * exists for the streamed file, its content is returned as-is - without parsing the
 * source, constructing any transformer or touching the container. Only a cache miss
 * builds the StreamMetaData and runs the transformer chain, persisting the result.
 *
 * @phpstan-property resource $stream Inherited from php_user_filter; typed here for static analysis
 */
class SourceTransformingLoader extends PhpStreamFilter
{
    /**
     * Php filter definition
     */
    public const string PHP_FILTER_READ = 'php://filter/read=';

    /**
     * Default PHP filter name for registration
     */
    public const string FILTER_IDENTIFIER = 'go.source.transforming.loader';

    /**
     * String buffer
     */
    protected string $data = '';

    /**
     * Transformer chain, assembled lazily from the container on the first cache miss
     *
     * @var SourceTransformer[]|null
     */
    protected static ?array $transformers = null;

    /**
     * Identifier of filter
     */
    protected static string $filterId;

    /**
     * Container that provides the transformer services and tracks resource freshness
     */
    private static ?AspectContainer $container = null;

    /**
     * Cache manager for querying/recording per-file transformation state
     */
    private static ?CachePathManager $cachePathManager = null;

    /**
     * Mask of enabled kernel features (see Features enumeration)
     */
    private static int $features = 0;

    /**
     * Register current loader as stream filter in PHP
     *
     * @throws RuntimeException If registration was failed
     */
    public static function register(string $filterId = self::FILTER_IDENTIFIER): void
    {
        if (!empty(self::$filterId)) {
            throw new WeavingException('Stream filter already registered');
        }

        $result = stream_filter_register($filterId, self::class);
        if ($result === false) {
            throw new WeavingException('Stream filter was not registered');
        }
        self::$filterId = $filterId;
    }

    /**
     * Brings up the transformation pipeline on demand: registers the stream filter and
     * remembers the collaborators needed for the cache decision and the (lazy) chain.
     *
     * Idempotent; called from the cache-miss paths (autoloader miss, include rewriting,
     * cache warmup), so a warm-cache request never registers the filter nor constructs
     * any transformer.
     */
    public static function ensureRegistered(AspectContainer $container): void
    {
        if (empty(self::$filterId)) {
            self::register();

            $kernelOptions = $container->getService(AspectKernel::class)->getOptions();

            self::$container        = $container;
            self::$cachePathManager = $container->getService(CachePathManager::class);
            self::$features         = $kernelOptions['features'];
        }
    }

    /**
     * Returns the name of registered filter
     *
     * @throws RuntimeException if filter was not registered
     */
    public static function getId(): string
    {
        if (empty(self::$filterId)) {
            throw new WeavingException('Stream filter was not registered');
        }

        return self::$filterId;
    }

    public function filter($in, $out, &$consumed, $closing): int
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $this->data .= $bucket->data;
        }

        if ($closing || feof($this->stream)) {
            $consumed = strlen($this->data);

            // $this->stream contains pointer to the source
            $originalUri = $this->resolveOriginalUri();
            $cacheUri    = self::$cachePathManager?->getCachePathForResource($originalUri);

            // Guard to disable overwriting of original files or when cache is unavailable:
            // the source passes through untouched (previously an aborted result in the wrapper)
            if ($cacheUri === null || $cacheUri === $originalUri) {
                stream_bucket_append($out, stream_bucket_new($this->stream, $this->data));

                return PSFS_PASS_ON;
            }

            // Cache hit: return the cached content as a result right from here -
            // no StreamMetaData, no parsing, no transformers
            $cachedContent = self::findCachedContent($originalUri, $cacheUri, $this->data);
            if ($cachedContent !== null) {
                stream_bucket_append($out, stream_bucket_new($this->stream, $cachedContent));

                return PSFS_PASS_ON;
            }

            // Cache miss: parse the source, run the transformer chain and persist the result
            $metadata = new StreamMetaData($this->stream, $this->data);
            $result   = self::transformCode($metadata);
            // An aborted chain reverts every change: the original source is served and recorded as untransformed
            $source   = $result === TransformerResult::Aborted ? $this->data : $metadata->source;
            self::saveToCache($originalUri, $cacheUri, $source, $result);

            stream_bucket_append($out, stream_bucket_new($this->stream, $source));

            return PSFS_PASS_ON;
        }

        return PSFS_FEED_ME;
    }

    /**
     * Transforms source code by passing it through all transformers
     *
     * @return TransformerResult Overall result: Transformed if at least one
     *         transformer transformed the source, Aborted if the chain was
     *         terminated, Abstain otherwise
     */
    public static function transformCode(StreamMetaData $metadata): TransformerResult
    {
        $overallResult = TransformerResult::Abstain;
        foreach (self::getTransformers() as $transformer) {
            $transformationResult = $transformer->transform($metadata);
            if ($overallResult === TransformerResult::Abstain
                && $transformationResult === TransformerResult::Transformed
            ) {
                $overallResult = TransformerResult::Transformed;
            }
            // transformer reported about termination, next transformers will be skipped
            if ($transformationResult === TransformerResult::Aborted) {
                $overallResult = TransformerResult::Aborted;
                break;
            }
        }

        return $overallResult;
    }

    /**
     * Extracts the original file path from the filtered stream
     *
     * Applies the same normalization as StreamMetaData, so cache records written from
     * metadata are queried with identical keys.
     */
    private function resolveOriginalUri(): string
    {
        $uri = stream_get_meta_data($this->stream)['uri'] ?? '';
        if (preg_match('/resource=(.+)$/', $uri, $matches)) {
            $resolvedUri = PathResolver::realpath($matches[1]);
            $uri         = is_string($resolvedUri) ? $resolvedUri : $matches[1];
        }

        return $uri;
    }

    /**
     * Tries to serve the file from the cache, returning null on a cache miss
     *
     * A hit returns the content to emit as-is: the woven cached file for a transformed
     * source, or the buffered original source for a file known to need no transformation.
     *
     * @param string $originalContent Buffered original source of the streamed file
     */
    private static function findCachedContent(string $originalUri, string $cacheUri, string $originalContent): ?string
    {
        $cacheState = self::$cachePathManager?->queryCacheState($originalUri);
        if ($cacheState === null) {
            return null;
        }

        // With a prebuilt cache (built at deploy time) an existing cache record is trusted
        // as-is: no filemtime or tracked-resource freshness checks - staleness is the
        // deployer's responsibility.
        $isTrustedCacheRecord = (self::$features & Features::PREBUILT_CACHE) !== 0;

        if (!$isTrustedCacheRecord) {
            // The record keeps the size and mtime of the source it was woven from: any difference, also an older
            // mtime restored by a deployment (rsync -t, checkout of an older revision), means the source changed
            $cachedAt = $cacheState['cachedAt'] ?? 0;
            $isStale  = ($cacheState['filemtime'] ?? null) !== filemtime($originalUri)
                || ($cacheState['filesize'] ?? null) !== filesize($originalUri)
                || (isset($cacheState['cacheUri']) && $cacheState['cacheUri'] !== $cacheUri)
                || !(self::$container?->isFreshSince(is_int($cachedAt) ? $cachedAt : 0) ?? false);
            if ($isStale) {
                return null;
            }
        }

        $recordedCacheUri = $cacheState['cacheUri'] ?? null;
        if (is_string($recordedCacheUri)) {
            $cachedContent = file_get_contents($recordedCacheUri);

            return $cachedContent === false ? null : $cachedContent;
        }

        // The file is known to the cache as untransformed - serve the original source
        return $originalContent;
    }

    /**
     * Persists the transformation outcome: the woven source for a transformed file,
     * or a "needs no transformation" record otherwise
     */
    private static function saveToCache(
        string $originalUri,
        string $cacheUri,
        string $transformedSource,
        TransformerResult $result,
    ): void {
        if (self::$cachePathManager === null) {
            return;
        }

        if ($result === TransformerResult::Transformed) {
            // A woven source carries the original class body as a `trait <Name>OriginalTrait`
            // declaration and is cached next to the generated proxy, under the same marker.
            // Both checks are anchored so that a class merely named `...OriginalTrait` is not
            // mistaken for a woven body.
            $originalBodyTrait = '/\btrait\s+\w+' . preg_quote(AspectContainer::ORIGINAL_TRAIT_SUFFIX, '/') . '\b/';
            if (!str_ends_with($cacheUri, AspectContainer::ORIGINAL_TRAIT_FILE_SUFFIX)
                && preg_match($originalBodyTrait, $transformedSource) === 1
            ) {
                $cacheUri = PathResolver::withSuffixBeforeExtension($cacheUri, AspectContainer::ORIGINAL_TRAIT_SUFFIX);
            }
            // Atomic write: a concurrent request including this file never sees a partial source
            self::$cachePathManager->getCacheFileWriter()->write($cacheUri, $transformedSource);
        }

        self::$cachePathManager->setCacheState(
            $originalUri,
            [
                'filemtime' => filemtime($originalUri),
                'filesize'  => filesize($originalUri),
                // Weaving time, compared with the tracked resources (kernel and aspect files)
                'cachedAt'  => $_SERVER['REQUEST_TIME'] ?? time(),
                'cacheUri'  => ($result === TransformerResult::Transformed) ? $cacheUri : null,
            ],
        );
    }

    /**
     * Assembles the transformer chain lazily on the first cache miss
     *
     * Tagged loading: every container service implementing SourceTransformer forms the
     * chain, in registration order. A kernel can plug its own transformer in with a
     * single addLazyService() call (see AspectKernel::registerTransformerServices()).
     *
     * @return SourceTransformer[]
     */
    private static function getTransformers(): array
    {
        if (self::$transformers === null) {
            self::$transformers = self::$container?->getServicesByInterface(SourceTransformer::class) ?? [];
        }

        return self::$transformers;
    }
}
