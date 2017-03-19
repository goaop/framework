<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\ClassLoading;

use php_user_filter as PhpStreamFilter;
use Go\Instrument\Transformer\StreamMetaData;
use Go\Instrument\Transformer\SourceTransformer;

/**
 * Php class loader filter for processing php code
 */
class SourceTransformingLoader extends PhpStreamFilter
{
    /**
     * Php filter definition
     */
    const PHP_FILTER_READ = 'php://filter/read=';

    /**
     * Default PHP filter name for registration
     */
    const FILTER_IDENTIFIER = 'go.source.transforming.loader';

    /**
     * String buffer
     *
     * @var string
     */
    protected $data = '';

    /**
     * List of transformers
     *
     * @var array|SourceTransformer[]
     */
    protected static $transformers = [];

    /**
     * Identifier of filter
     *
     * @var string
     */
    protected static $filterId;

    /**
     * Register current loader as stream filter in PHP
     *
     * @param string $filterId Identifier for the filter
     * @throws \RuntimeException If registration was failed
     */
    public static function register($filterId = self::FILTER_IDENTIFIER)
    {
        if (!empty(self::$filterId)) {
            throw new \RuntimeException('Stream filter already registered');
        }

        $result = stream_filter_register($filterId, __CLASS__);
        if (!$result) {
            throw new \RuntimeException('Stream filter was not registered');
        }
        self::$filterId = $filterId;
    }

    /**
     * Returns the name of registered filter
     *
     * @throws \RuntimeException if filter was not registered
     * @return string
     */
    public static function getId()
    {
        if (empty(self::$filterId)) {
            throw new \RuntimeException('Stream filter was not registered');
        }

        return self::$filterId;
    }

    /**
     * {@inheritdoc}
     */
    public function filter($in, $out, &$consumed, $closing)
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
     *
     * @param $transformer SourceTransformer Transformer for source code
     *
     * @return void
     */
    public static function addTransformer(SourceTransformer $transformer)
    {
        self::$transformers[] = $transformer;
    }

    /**
     * Transforms source code by passing it through all transformers
     *
     * @param StreamMetaData|null $metadata Metadata from stream
     * @return void|bool Return false if transformation should be stopped
     */
    public static function transformCode(StreamMetaData $metadata)
    {
        $result = null;
        foreach (self::$transformers as $transformer) {
            $result = $transformer->transform($metadata);
            if ($result === false) {
                break;
            }
        }

        return $result;
    }
}
