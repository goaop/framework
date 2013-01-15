<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Instrument\ClassLoading;

use php_user_filter as PhpStreamFilter;

use Go\Instrument\Transformer\StreamMetaData;
use Go\Instrument\Transformer\SourceTransformer;
use Go\Instrument\Transformer\FilterInjectorTransformer;

/**
 * Php class loader filter for processing php code
 */
class SourceTransformingLoader extends PhpStreamFilter implements LoadTimeWeaver
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
     * Filter bucket resource
     *
     * @var null|resource
     */
    protected $bucket = null;

    /**
     * List of transformers
     *
     * @var array|SourceTransformer[]
     */
    protected static $transformers = array();

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
    public function register($filterId = self::FILTER_IDENTIFIER)
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
    public function getId()
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
            $this->bucket = $bucket;
            $consumed = 0;
        }

        if ($closing) {
            $consumed += strlen($this->data);

            // $this->stream contains pointer to the source
            $metadata = new StreamMetaData($this->stream);
            $metadata->source = $this->data;

            $this->transformCode($metadata);
            $this->bucket->data = $metadata->source;
            $this->bucket->datalen = strlen($this->bucket->data);
            if (!empty($this->bucket->data)) {
                stream_bucket_append($out, $this->bucket);
            }
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
    public function addTransformer(SourceTransformer $transformer)
    {
        self::$transformers[] = $transformer;
    }

    /**
     * Load source file with transformation
     *
     * @param string $source Original source name
     *
     * @return mixed
     */
    public function load($source)
    {
        return include FilterInjectorTransformer::rewrite($source);
    }

    /**
     * Transforms source code by passing it through all transformers
     *
     * @param StreamMetaData|null $metadata Metadata from stream
     * @return void
     */
    protected function transformCode(StreamMetaData $metadata)
    {
        foreach (self::$transformers as $transformer) {
            $transformer->transform($metadata);
        }
    }
}
