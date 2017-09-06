<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer;

use Go\Instrument\PathResolver;
use Go\ParserReflection\ReflectionEngine;
use InvalidArgumentException;
use PhpParser\Node;

/**
 * Stream metadata object
 *
 * @property-read string $source
 */
class StreamMetaData
{
    /**
     * Mapping between array keys and properties
     *
     * @var array
     */
    private static $propertyMap = [
        'stream_type'  => 'streamType',
        'wrapper_type' => 'wrapperType',
        'wrapper_data' => 'wrapperData',
        'filters'      => 'filterList',
        'uri'          => 'uri',
    ];

    /**
     * A label describing the underlying implementation of the stream.
     *
     * @var string
     */
    public $streamType;

    /**
     * A label describing the protocol wrapper implementation layered over the stream.
     *
     * @var string
     */
    public $wrapperType;

    /**
     * Wrapper-specific data attached to this stream.
     *
     * @var mixed
     */
    public $wrapperData;

    /**
     * Array containing the names of any filters that have been stacked onto this stream.
     *
     * @var array
     */
    public $filterList;

    /**
     * The URI/filename associated with this stream.
     *
     * @var string
     */
    public $uri;

    /**
     * Information about syntax tree
     *
     * @var Node[]
     */
    public $syntaxTree;

    /**
     * List of source tokens
     *
     * @var array
     */
    public $tokenStream = [];

    /**
     * Creates metadata object from stream
     *
     * @param resource $stream Instance of stream
     * @param string $source Source code or null
     * @throws \InvalidArgumentException for invalid stream
     */
    public function __construct($stream, $source = null)
    {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException('Stream should be valid resource');
        }
        $metadata = stream_get_meta_data($stream);
        if (preg_match('/resource=(.+)$/', $metadata['uri'], $matches)) {
            $metadata['uri'] = PathResolver::realpath($matches[1]);
        }
        foreach ($metadata as $key => $value) {
            if (!isset(self::$propertyMap[$key])) {
                continue;
            }
            $mappedKey = self::$propertyMap[$key];
            $this->$mappedKey = $value;
        }
        $this->syntaxTree = ReflectionEngine::parseFile($this->uri, $source);
        $this->setSource($source);
    }

    /**
     * @inheritDoc
     */
    public function __get($name)
    {
        if ($name === 'source') {
            return $this->getSource();
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function __set($name, $value)
    {
        if ($name === 'source') {
            trigger_error('Setting StreamMetaData->source is deprecated, use tokenStream instead', E_USER_DEPRECATED);
            $this->setSource($value);
        }
    }

    /**
     * Returns source code directly from tokens
     *
     * @return string
     */
    private function getSource()
    {
        $transformedSource = '';
        foreach ($this->tokenStream as $token) {
            $transformedSource .= isset($token[1]) ? $token[1] : $token;
        }

        return $transformedSource;
    }

    /**
     * Sets the new source for this file
     *
     * @TODO: Unfortunately, AST won't be changed, so please be accurate during transformation
     *
     * @param string $newSource
     */
    private function setSource($newSource)
    {
        $rawTokens = token_get_all($newSource);
        foreach ($rawTokens as $index => $rawToken) {
            $this->tokenStream[$index] = \is_array($rawToken) ? $rawToken : [T_STRING, $rawToken];
        }
    }
}
