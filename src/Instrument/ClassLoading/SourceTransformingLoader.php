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

use Go\Core\AspectKernel;
use Go\Instrument\FileSystem\FileCachePool;
use Go\Instrument\Transformer\IncludeNodeWrapperVisitor;
use Go\Instrument\Transformer\MagicDirFileConstantReplaceVisitor;
use Go\Instrument\Transformer\SelfValueVisitor;
use Go\Instrument\Transformer\SourceTransformer;
use Go\Instrument\Transformer\StreamMetaData;
use Go\Instrument\Transformer\TransformerResultEnum;
use php_user_filter as PhpStreamFilter;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\PrettyPrinter\Standard;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;

use function strlen;

/**
 * Php class loader filter for processing php code
 */
final class SourceTransformingLoader extends PhpStreamFilter
{
    /**
     * Default PHP filter name for registration
     */
    public const FILTER_IDENTIFIER = 'go.source.transforming.loader';

    private string $filterBuffer = '';

    /**
     * @var SourceTransformer[] List of transformers
     */
    private static array $transformers = [];

    /**
     * Identifier of filter
     */
    private static string $registeredFilterIdentifier;

    /**
     * Register current loader as stream filter in PHP
     *
     * @throws RuntimeException If registration was failed
     */
    public static function register(string $filterId = self::FILTER_IDENTIFIER): void
    {
        $result = stream_filter_register($filterId, self::class);
        if ($result === false) {
            throw new RuntimeException('Stream filter was not registered, possibly already registered');
        }
        self::$registeredFilterIdentifier = $filterId;
    }

    /**
     * Returns the name of registered filter
     *
     * @throws RuntimeException if filter was not registered
     */
    public static function getId(): string
    {
        if (!isset(self::$registeredFilterIdentifier)) {
            throw new RuntimeException('Stream filter was not registered');
        }

        return self::$registeredFilterIdentifier;
    }

    public function filter($in, $out, &$consumed, bool $closing): int
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $this->filterBuffer .= $bucket->data;
        }

        if ($closing || feof($this->stream)) {
            $consumed = strlen($this->filterBuffer);

            $metadata = new StreamMetaData($this->stream, $this->filterBuffer);
            // here we should check if there is a version in the cache and it's fresh, then we can bypass AST analysis below
//            if (true || !cacheIsFresh($metadata)) {
                $source = self::transformCode($metadata);
                // Store somehow in the cache
//            }
            // otherwise we can use cached version

//            $bucket = stream_bucket_new($this->stream, $source);
            $bucket = stream_bucket_new($this->stream, $metadata->source);
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
     * Transforms source code by passing it through all transformers
     */
    private static function transformCode(StreamMetaData $metadata): void
    {

//        $traverser = new NodeTraverser(
//            // Run CloningVisitor before making changes to the AST to be able to reconstruct code again
//            new CloningVisitor(),
//            new IncludeNodeWrapperVisitor('var_dump'),
//            new MagicDirFileConstantReplaceVisitor($metadata),
//            new SelfValueVisitor()
//        );
//        $newSyntaxTree = $traverser->traverse($metadata->syntaxTree);
//
//        $printer = new Standard();
//        $source  = $printer->printFormatPreserving($newSyntaxTree, $metadata->syntaxTree, $metadata->originalTokenStream);
//
//        return $source;
//
        foreach (self::$transformers as $transformer) {
            $result = $transformer->transform($metadata);
            if ($result === TransformerResultEnum::RESULT_ABORTED) {
                break;
            }
        }
    }
}
