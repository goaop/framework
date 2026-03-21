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
use function is_resource;

/**
 * Stream metadata object
 *
 * @property-read string $source
 */
class StreamMetaData
{
    /**
     * A label describing the underlying implementation of the stream.
     */
    readonly public string $streamType;

    /**
     * A label describing the protocol wrapper implementation layered over the stream.
     */
    readonly public string $wrapperType;

    /**
     * The URI/filename associated with this stream.
     */
    readonly public string $uri;

    /**
     * List of original source tokens
     *
     * @var PhpToken[]
     */
    readonly public array $originalTokenStream;

    /**
     * @var Node[] Mutable syntax tree
     */
    public array $syntaxTree;

    /**
     * Mutable list of source tokens
     *
     * @var PhpToken[]
     */
    public array $tokenStream = [];

    /**
     * Creates metadata object from stream
     *
     * @param resource $stream Instance of stream
     * @param string $streamSource Source code
     * @throws InvalidArgumentException for invalid stream
     */
    public function __construct($stream, string $streamSource)
    {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException('Stream should be valid resource');
        }
        $metadata = stream_get_meta_data($stream);
        if (preg_match('/resource=(.+)$/', $metadata['uri'], $matches)) {
            $metadata['uri'] = PathResolver::realpath($matches[1]);
        }
        // Mostly, we need only "uri" from the stream metadata
        $this->streamType  = $metadata['stream_type'];
        $this->wrapperType = $metadata['wrapper_type'];
        $this->uri         = $metadata['uri'];

        $this->syntaxTree = ReflectionEngine::parseFile($this->uri, $streamSource);

        $this->originalTokenStream = ReflectionEngine::getParser()->getTokens();
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
     * Sets an array of token identifiers for this file
     */
    public function setTokenStreamFromRawTokens(PhpToken ...$rawTokens): void
    {
        foreach ($rawTokens as $index => $rawToken) {
            $this->tokenStream[$index] = clone $rawToken;
        }
    }
}
