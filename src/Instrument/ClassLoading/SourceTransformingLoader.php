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

use Go\Instrument\Transformer\SourceTransformer;
use Go\Instrument\Transformer\StreamMetaData;
use php_user_filter as PhpStreamFilter;
use RuntimeException;

use function strlen;

/**
 * Php class loader filter for processing php code
 *
 * @property resource $stream Stream instance of underlying resource
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
     * Identifier of filter
     */
    protected static string $filterId;

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

            // $this->stream contains pointer to the source
            $metadata = new StreamMetaData($this->stream, $this->data);
            self::transformCode($metadata);

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
    public static function transformCode(StreamMetaData $metadata): void
    {
        foreach (self::$transformers as $transformer) {
            $result = $transformer->transform($metadata);
            if ($result === SourceTransformer::RESULT_ABORTED) {
                break;
            }
        }
    }
}
