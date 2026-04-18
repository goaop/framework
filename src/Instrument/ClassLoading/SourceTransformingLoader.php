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
use Go\Instrument\PathResolver;
use Go\Instrument\Transformer\FileNameInjectorNodeVisitor;
use Go\Instrument\Transformer\NodeTransformerResultReporter;
use Go\Instrument\Transformer\StreamMetaData;
use Go\Instrument\Transformer\TransformerResultEnum;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\PrettyPrinter\Standard;
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
     * List of node visitors
     *
     * @var array<int, NodeVisitor&NodeTransformerResultReporter>
     */
    protected static array $nodeVisitors = [];

    /**
     * Identifier of filter
     */
    protected static string $filterId;
    private static ?CachePathManager $cachePathManager = null;
    private static ?AspectContainer $container = null;
    private static int $cacheFileMode = 0770;

    /**
     * Register current loader as stream filter in PHP
     *
     * @throws RuntimeException If registration was failed
     */
    public static function register(string $filterId = self::FILTER_IDENTIFIER): void
    {
        if (!empty(self::$filterId)) {
            throw new RuntimeException('Stream filter already registered');
        }

        $result = stream_filter_register($filterId, self::class);
        if ($result === false) {
            throw new RuntimeException('Stream filter was not registered');
        }
        self::$filterId = $filterId;
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

            $cachedSource = self::getFreshCachedSource($this->stream);
            if ($cachedSource !== null) {
                $bucket = stream_bucket_new($this->stream, $cachedSource);
                stream_bucket_append($out, $bucket);

                return PSFS_PASS_ON;
            }

            $metadata = new StreamMetaData($this->stream, $this->data);
            $newSyntaxTree = self::transformCode($metadata);
            $overallResult = TransformerResultEnum::RESULT_ABSTAIN;
            foreach (self::$nodeVisitors as $visitor) {
                $visitorResult = $visitor->getNodeTransformerResult();
                if ($overallResult === TransformerResultEnum::RESULT_ABSTAIN && $visitorResult === TransformerResultEnum::RESULT_TRANSFORMED) {
                    $overallResult = TransformerResultEnum::RESULT_TRANSFORMED;
                }
            }
            $prettyPrinter = new Standard();
            $resultSource = $prettyPrinter->printFormatPreserving($newSyntaxTree, $metadata->syntaxTree, $metadata->tokenStream);

            $wasTransformed = $overallResult === TransformerResultEnum::RESULT_TRANSFORMED;
            self::writeCache($metadata, $resultSource, $wasTransformed);

            $bucket = stream_bucket_new($this->stream, $resultSource);
            stream_bucket_append($out, $bucket);

            return PSFS_PASS_ON;
        }

        return PSFS_FEED_ME;
    }

    /**
     * Adds a NodeVisitor to be applied by traverser.
     */
    public static function addNodeVisitor(NodeVisitor&NodeTransformerResultReporter $visitor): void
    {
        self::$nodeVisitors[] = $visitor;
    }

    /**
     * Configures cache support for stream loader.
     */
    public static function configureCache(CachePathManager $cachePathManager, AspectContainer $container, int $cacheFileMode): void
    {
        self::$cachePathManager = $cachePathManager;
        self::$container        = $container;
        self::$cacheFileMode    = $cacheFileMode;
    }

    /**
     * Transforms source code by applying all registered node visitors in one traversal pass.
     *
     * The traversal starts with FileNameInjectorNodeVisitor, which injects the original file name
     * and StreamMetaData DTO into top-level namespace nodes. Other visitors read this contextual
     * metadata from namespace attributes and report transformation status through
     * NodeTransformerResultReporter.
     *
     * @return Node[]
     */
    public static function transformCode(StreamMetaData $metadata): array
    {
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new FileNameInjectorNodeVisitor($metadata));
        foreach (self::$nodeVisitors as $visitor) {
            $traverser->addVisitor($visitor);
        }
        $newSyntaxTree = $traverser->traverse($metadata->syntaxTree);

        return $newSyntaxTree;
    }

    /**
     * Returns cached transformed source when cache entry is fresh for the supplied stream.
     *
     * This check runs before AST/token parsing to preserve fast-path performance.
     *
     * @param resource|mixed $stream
     */
    private static function getFreshCachedSource(mixed $stream): ?string
    {
        if (self::$cachePathManager === null || self::$container === null) {
            return null;
        }
        if (!is_resource($stream)) {
            return null;
        }

        $streamMeta  = stream_get_meta_data($stream);
        $originalUri = $streamMeta['uri'];
        if ($originalUri === '') {
            return null;
        }
        if (preg_match('/resource=(.+)$/', $originalUri, $matches)) {
            $resolvedUri = PathResolver::realpath($matches[1]);
            if ($resolvedUri === false) {
                return null;
            }
            $originalUri = $resolvedUri;
        }
        if ($originalUri === '' || !file_exists($originalUri)) {
            return null;
        }

        $cacheUri = self::$cachePathManager->getCachePathForResource($originalUri);
        if ($cacheUri === false || $cacheUri === $originalUri || !file_exists($cacheUri)) {
            return null;
        }

        $cacheState = self::$cachePathManager->queryCacheState($originalUri);
        if ($cacheState === null) {
            return null;
        }

        $cacheFilemtime = $cacheState['filemtime'] ?? 0;
        $cacheModified  = is_int($cacheFilemtime) ? $cacheFilemtime : 0;
        $originalFilemtime = filemtime($originalUri);
        $lastModified      = is_int($originalFilemtime) ? $originalFilemtime : 0;
        $isFresh = $cacheModified >= $lastModified
            && (($cacheState['cacheUri'] ?? null) === $cacheUri)
            && !self::$container->hasAnyResourceChangedSince($cacheModified);
        if (!$isFresh) {
            return null;
        }

        $cachedSource = file_get_contents($cacheUri);
        if ($cachedSource === false) {
            return null;
        }

        return $cachedSource;
    }

    /**
     * Persists transformed source and cache metadata for the current stream metadata DTO.
     */
    private static function writeCache(StreamMetaData $streamMetadata, string $source, bool $wasTransformed): void
    {
        if (self::$cachePathManager === null || self::$container === null) {
            return;
        }
        $originalUri = $streamMetadata->uri;
        $cacheUri = self::$cachePathManager->getCachePathForResource($originalUri);
        if ($cacheUri === false || $cacheUri === $originalUri) {
            return;
        }

        if ($wasTransformed) {
            $parentCacheDir = dirname($cacheUri);
            if (!is_dir($parentCacheDir)) {
                mkdir($parentCacheDir, self::$cacheFileMode, true);
            }
            file_put_contents($cacheUri, $source, LOCK_EX);
            chmod($cacheUri, self::$cacheFileMode & (~0111));
        }

        self::$cachePathManager->setCacheState(
            $originalUri,
            [
                'filemtime' => $_SERVER['REQUEST_TIME'] ?? time(),
                'cacheUri'  => $wasTransformed ? $cacheUri : null,
            ]
        );
    }
}
