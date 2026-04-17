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
use PhpParser\PrettyPrinter\Standard;
use PhpParser\Token;
use PhpToken;
use function is_resource;

/**
 * Stream metadata object
 */
class StreamMetaData
{
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
     * @var array<int, PhpToken|Token>
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
        if (isset($metadata['uri']) && preg_match('/resource=(.+)$/', $metadata['uri'], $matches)) {
            $metadata['uri'] = PathResolver::realpath($matches[1]);
        }
        foreach ($metadata as $key => $value) {
            if (!isset(self::$propertyMap[$key])) {
                continue;
            }
            $mappedKey = self::$propertyMap[$key];
            $this->$mappedKey = $value;
        }
        $this->reloadFromSource($source);
    }

    /**
     * Reload metadata syntax tree and tokens from source code.
     */
    public function reloadFromSource(?string $source = null): void
    {
        $this->syntaxTree = ReflectionEngine::parseFile($this->uri, $source);
        $this->setTokenStreamFromRawTokens(...ReflectionEngine::getParser()->getTokens());
    }

    /**
     * Rebuild syntax tree from the current token stream contents.
     */
    public function refreshSyntaxTreeFromTokenStream(): void
    {
        $this->reloadFromSource($this->getTransformedSource());
    }

    /**
     * Applies updated syntax tree and rebuilds tokens using format-preserving pretty printer.
     */
    public function applySyntaxTree(array $newSyntaxTree): void
    {
        $prettyPrinter      = new Standard();
        $transformedSource  = $prettyPrinter->printFormatPreserving($newSyntaxTree, $this->syntaxTree, $this->tokenStream);
        $this->reloadFromSource($transformedSource);
    }

    /**
     * Returns transformed source code directly from tokens.
     */
    public function getTransformedSource(): string
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
    public function setTokenStreamFromRawTokens(PhpToken|Token ...$rawTokens): void
    {
        $this->tokenStream = $rawTokens;
    }
}
