<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace go\core;

use go\instrument\ClassFileTransformer;

/**
 * Php class loader filter for processing php code
 *
 * @package go
 * @subpackage core
 */
class ClassLoader extends \php_user_filter implements \go\instrument\classloading\LoadTimeWeaver
{
    /** @var string String buffer */
    protected $data = '';

    /** @var null|resource */
    protected $bucket = null;

    /** @var string Name of class to load  */
    protected $className = '';

    /** @var array|\go\instrument\ClassFileTransformer[] */
    protected static $transformers = array();

    public function onCreate()
    {
        $filterPathElements = explode('.', $this->filtername);
        $this->className = isset($filterPathElements[3]) ? $filterPathElements[3] : '\__PHP_Incomplete_Class';
    }

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
     * @param $transformer \go\instrument\ClassFileTransformer Transformer for source code
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
     * @return string Transformed source code
     */
    protected function transformCode($code)
    {
        $soureTokens = token_get_all($code);
        foreach (self::$transformers as $transformer) {
            $soureTokens = $transformer->transform($this->className, $soureTokens);
        }
        $transformedSourceCode = array_reduce($soureTokens, function ($code, $token) {
            return $code . (is_array($token) ? $token[1] : $token);
        }, '');
        return $transformedSourceCode;
    }
}