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
use InvalidArgumentException;

/**
 * Stream metadata object
 */
class StreamMetaData
{
    /**
     * Mapping between array keys and properties
     *
     * @var array
     */
    private static $propertyMap = [
        'timed_out'    => 'isTimedOut',
        'blocked'      => 'isBlocked',
        'eof'          => 'isEOF',
        'unread_bytes' => 'unreadBytesCount',
        'stream_type'  => 'streamType',
        'wrapper_type' => 'wrapperType',
        'wrapper_data' => 'wrapperData',
        'filters'      => 'filterList',
        'mode'         => 'mode',
        'seekable'     => 'isSeekable',
        'uri'          => 'uri',
    ];

    /**
     * TRUE if the stream timed out while waiting for data on the last call to fread() or fgets().
     *
     * @var bool
     */
    public $isTimedOut;

    /**
     * TRUE if the stream has reached end-of-file.
     *
     * @var bool
     */
    public $isBlocked;

    /**
     * TRUE if the stream has reached end-of-file.
     *
     * @var bool
     */
    public $isEOF;

    /**
     * The number of bytes currently contained in the PHP's own internal buffer.
     *
     * @var integer
     */
    public $unreadBytesCount;

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
     * The type of access required for this stream
     *
     * @var string
     */
    public $mode;

    /**
     * Whether the current stream can be seeked.
     *
     * @var bool
     */
    public $isSeekable;

    /**
     * The URI/filename associated with this stream.
     *
     * @var string
     */
    public $uri;

    /**
     * The contents of the stream.
     *
     * @var string
     */
    public $source;

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
            throw new InvalidArgumentException("Stream should be valid resource");
        }
        $metadata     = stream_get_meta_data($stream);
        $this->source = $source;
        if (preg_match('/resource=(.+)$/', $metadata['uri'], $matches)) {
            $metadata['uri'] = PathResolver::realpath($matches[1]);
        }
        foreach ($metadata as $key=>$value) {
            $mappedKey = self::$propertyMap[$key];
            $this->$mappedKey = $value;
        }
    }
}
