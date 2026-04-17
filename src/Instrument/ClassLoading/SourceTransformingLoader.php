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
use Go\Instrument\Transformer\SourceTransformer;
use Go\Instrument\Transformer\StreamMetaData;
use Go\Instrument\Transformer\TransformerResultEnum;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\CloningVisitor;
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
     * List of transformers
     *
     * @var SourceTransformer[]
     */
    protected static array $transformers = [];
    /**
     * List of node visitors
     *
     * @var NodeVisitor[]
     */
    protected static array $nodeVisitors = [];

    /**
     * Identifier of filter
     */
    protected static string $filterId;
    private static ?CachePathManager $cachePathManager = null;
    private static ?AspectContainer $container = null;
    private static int $cacheFileMode = 0770;
    private static bool $lastAstTransformed = false;

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

            $prettyPrinter = new Standard();
            $astSource = $prettyPrinter->printFormatPreserving($newSyntaxTree, $metadata->syntaxTree, $metadata->tokenStream);

            $legacyMetadata = new StreamMetaData($this->stream, $astSource);
            $legacyResult   = self::applyLegacyTransformers($legacyMetadata);
            $resultSource   = self::stringifyTokens($legacyMetadata->tokenStream);

            $wasTransformed = self::$lastAstTransformed || $legacyResult === TransformerResultEnum::RESULT_TRANSFORMED;
            self::writeCache($metadata->uri, $resultSource, $wasTransformed);

            $bucket = stream_bucket_new($this->stream, $resultSource);
            stream_bucket_append($out, $bucket);

            return PSFS_PASS_ON;
        }

        return PSFS_FEED_ME;
    }

    /**
     * Adds a SourceTransformer to be applied by this LoadTimeWeaver.
     */
    public static function addTransformer(SourceTransformer $transformer): void
    {
        self::$transformers[] = $transformer;
    }

    /**
     * Adds a NodeVisitor to be applied by traverser.
     */
    public static function addNodeVisitor(NodeVisitor $visitor): void
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
     * Transforms source code by passing it through all node visitors and returns transformed AST.
     *
     * @return Node[]
     */
    public static function transformCode(StreamMetaData $metadata): array
    {
        self::$lastAstTransformed = false;
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new CloningVisitor());
        foreach (self::$nodeVisitors as $visitor) {
            if (method_exists($visitor, 'setCurrentFileName')) {
                $visitor->setCurrentFileName($metadata->uri);
            }
            $traverser->addVisitor($visitor);
        }
        $newSyntaxTree = $traverser->traverse($metadata->syntaxTree);
        foreach (self::$nodeVisitors as $visitor) {
            if (method_exists($visitor, 'hasChanges') && $visitor->hasChanges()) {
                self::$lastAstTransformed = true;
                break;
            }
        }

        return $newSyntaxTree;
    }

    private static function applyLegacyTransformers(StreamMetaData $metadata): TransformerResultEnum
    {
        $overallResult = TransformerResultEnum::RESULT_ABSTAIN;
        foreach (self::$transformers as $transformer) {
            $result = $transformer->transform($metadata);
            if ($overallResult === TransformerResultEnum::RESULT_ABSTAIN && $result === TransformerResultEnum::RESULT_TRANSFORMED) {
                $overallResult = TransformerResultEnum::RESULT_TRANSFORMED;
            }
            if ($result === TransformerResultEnum::RESULT_ABORTED) {
                break;
            }
        }

        return $overallResult;
    }

    private static function stringifyTokens(array $tokens): string
    {
        $result = '';
        foreach ($tokens as $token) {
            if ($token->id !== 0) {
                $result .= $token->text;
            }
        }

        return $result;
    }

    private static function getFreshCachedSource(mixed $stream): ?string
    {
        if (self::$cachePathManager === null || self::$container === null) {
            return null;
        }
        if (!is_resource($stream)) {
            return null;
        }

        $streamMeta = stream_get_meta_data($stream);
        if (!is_array($streamMeta) || !isset($streamMeta['uri']) || !is_string($streamMeta['uri'])) {
            return null;
        }

        $originalUri = $streamMeta['uri'];
        if (preg_match('/resource=(.+)$/', $originalUri, $matches)) {
            $originalUri = PathResolver::realpath($matches[1]);
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
            && self::$container->hasAnyResourceChangedSince($cacheModified);
        if (!$isFresh) {
            return null;
        }

        $cachedSource = file_get_contents($cacheUri);
        if ($cachedSource === false) {
            return null;
        }

        return $cachedSource;
    }

    private static function writeCache(string $originalUri, string $source, bool $wasTransformed): void
    {
        if (self::$cachePathManager === null || self::$container === null) {
            return;
        }
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
