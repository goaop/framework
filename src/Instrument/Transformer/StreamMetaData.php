<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer;

use Go\Aop\Exception\WeavingException;
use Go\Instrument\PathResolver;
use Go\ParserReflection\ReflectionEngine;
use InvalidArgumentException;
use PhpParser\Node;
use PhpToken;

use function is_resource;

/**
 * Stream metadata object
 */
final class StreamMetaData
{
    /**
     * Source code represented by the token stream, rebuilt from {@see self::$tokenStream} on every read.
     *
     * The property is read-only: transformers change the token stream, or replace it through
     * {@see self::setTokenStreamFromRawTokens()}.
     */
    public string $source {
        get {
            $transformedSource = '';
            foreach ($this->tokenStream as $token) {
                if ($token->id !== 0) {
                    $transformedSource .= $token->text;
                }
            }

            return $transformedSource;
        }
    }

    /**
     * Mapping between array keys and properties
     *
     * @var array<string, string>
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
     */
    public mixed $wrapperData;

    /**
     * Array containing the names of any filters that have been stacked onto this stream.
     *
     * @var string[]
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
     * Source code the syntax tree was parsed from, before any transformation
     */
    public readonly string $originalSource;

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
            throw new WeavingException('Stream should be valid resource');
        }
        $metadata = stream_get_meta_data($stream);
        if (!isset($metadata['uri'])) {
            throw new WeavingException('Stream has no uri, only file streams can be transformed');
        }
        if (preg_match('/resource=(.+)$/', $metadata['uri'], $matches)) {
            $resolvedUri     = PathResolver::realpath($matches[1]);
            $metadata['uri'] = is_string($resolvedUri) ? $resolvedUri : $matches[1];
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
        $this->originalSource = $source ?? $this->source;
    }

    /**
     * Sets an array of token identifiers for this file
     */
    public function setTokenStreamFromRawTokens(PhpToken ...$rawTokens): void
    {
        $this->tokenStream = $rawTokens;
    }
}
