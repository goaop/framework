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

use ArrayObject;
use Go\Instrument\PathResolver;
use InvalidArgumentException;

/**
 * Stream metadata object
 *
 * @property bool   $timed_out     TRUE if the stream timed out while waiting for data on the last call to fread() or fgets().
 * @property bool   $blocked       TRUE if the stream is in blocking IO mode
 * @property bool   $eof           TRUE if the stream has reached end-of-file.
 * @property int    $unread_bytes  The number of bytes currently contained in the PHP's own internal buffer.
 * @property string $stream_type   A label describing the underlying implementation of the stream.
 * @property string $wrapper_type  A label describing the protocol wrapper implementation layered over the stream.
 * @property mixed  $wrapper_data  Wrapper-specific data attached to this stream.
 * @property array  $filters       Array containing the names of any filters that have been stacked onto this stream.
 * @property string $mode          The type of access required for this stream
 * @property bool   $seekable      Whether the current stream can be seeked.
 * @property string $uri           The URI/filename associated with this stream.
 * @property string $source        The contents of the stream.
 */
class StreamMetaData extends ArrayObject
{
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
        $metadata = stream_get_meta_data($stream);
        if ($source) {
            $metadata['source'] = $source;
        }
        if (preg_match('/resource=(.+)$/', $metadata['uri'], $matches)) {
            $metadata['uri'] = PathResolver::realpath($matches[1]);
        }
        parent::__construct($metadata, ArrayObject::ARRAY_AS_PROPS);
    }
}
