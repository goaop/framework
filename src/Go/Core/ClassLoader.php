<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Core;

use php_user_filter as PhpStreamFilter;

use Go\Instrument\ClassFileTransformer;
use Go\Instrument\ClassLoading\LoadTimeWeaver;

/**
 * Php class loader filter for processing php code
 *
 * @package go
 * @subpackage core
 */
class ClassLoader extends PhpStreamFilter implements LoadTimeWeaver
{
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
     * Name of class to load
     *
     * @var string
     */
    protected $className = '';

    /**
     * List of transformers
     *
     * @var array|ClassFileTransformer[]
     */
    protected static $transformers = array();

    /**
     * {@inheritdoc}
     */
    public function onCreate()
    {
        $filterPathElements = explode('.', $this->filtername);
        $this->className    = isset($filterPathElements[3]) ? $filterPathElements[3] : '\__PHP_Incomplete_Class';
    }

    /**
     * {@inheridoc}
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
            $this->bucket->data = $this->transformCode($this->data);
            $this->bucket->datalen = strlen($this->bucket->data);
            if (!empty($this->bucket->data)) {
                stream_bucket_append($out, $this->bucket);
            }
            return PSFS_PASS_ON;
        }

        return PSFS_FEED_ME;
    }

    /**
     * Adds a ClassFileTransformer to be applied by this LoadTimeWeaver.
     *
     * @param $transformer ClassFileTransformer Transformer for source code
     *
     * @return void
     */
    public static function addTransformer(ClassFileTransformer $transformer)
    {
        self::$transformers[] = $transformer;
    }

    /**
     * Transforms source code by passing it through all transformers
     *
     * @param string $code Source code
     *
     * @return string Transformed source code
     */
    protected function transformCode($code)
    {
        $transformedSourceCode = $code;
        if (self::$transformers) {
            $sourceTokens = token_get_all($code);
            foreach (self::$transformers as $transformer) {
                $sourceTokens = $transformer->transform($this->className, $sourceTokens);
            }
            $transformedSourceCode = array_reduce($sourceTokens, function ($code, $token) {
                return $code . (is_array($token) ? $token[1] : $token);
            }, '');
        }
        return $transformedSourceCode;
    }
}