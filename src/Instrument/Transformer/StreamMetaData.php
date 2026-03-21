<?php

declare(strict_types = 1);
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
use PhpToken;
use function is_array, is_resource;

/**
 * Stream metadata object
 *
 * @property-read string $source
 */
class StreamMetaData
{
    /**
     * Mapping between array keys and properties
     */
    private static array $propertyMap = [
        'stream_type'  => 'streamType',
        'wrapper_type' => 'wrapperType',
        'wrapper_data' => 'wrapperData',
        'filters'      => 'filterList',
        'uri'          => 'uri',
    ];

    /**
     * A label describing the underlying implementation of the stream.
     */
    public string $streamType;

    /**
     * A label describing the protocol wrapper implementation layered over the stream.
     */
    public string $wrapperType;

    /**
     * Wrapper-specific data attached to this stream.
     *
     * @var mixed
     */
    public $wrapperData;

    /**
     * Array containing the names of any filters that have been stacked onto this stream.
     */
    public array $filterList;

    /**
     * The URI/filename associated with this stream.
     */
    public string $uri;

    /**
     * Information about syntax tree
     *
     * @var Node[]
     */
    public array $syntaxTree;

    /**
     * List of source tokens
     *
     * @var PhpToken[]
     */
    public array $tokenStream = [];

    /**
     * Creates metadata object from stream
     *
     * @param resource $stream Instance of stream
     * @param string $source Source code or null
     * @throws InvalidArgumentException for invalid stream
     */
    public function __construct($stream, ?string $source = null)
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
        $this->setTokenStreamFromRawTokens(...ReflectionEngine::getParser()->getTokens());
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
     */
    private function getSource(): string
    {
        $transformedSource = '';
        foreach ($this->tokenStream as $token) {
            if ($token->id !== 0) {
                $transformedSource .= $token->text;
            }
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
    private function setSource(string $newSource): void
    {
        $rawTokens = PhpToken::tokenize($newSource);
        $this->setTokenStreamFromRawTokens(...$rawTokens);
    }

    /**
     * Sets an array of token identifiers for this file
     */
    public function setTokenStreamFromRawTokens(PhpToken ...$rawTokens): void
    {
        $this->tokenStream = $rawTokens;
    }
}
