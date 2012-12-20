<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Instrument\Transformer;

use ArrayObject;
use InvalidArgumentException;

/**
 * Stream metadata object
 *
 * @property bool timed_out TRUE if the stream timed out while waiting for data on the last call to fread() or fgets().
 * @property bool blocked TRUE if the stream is in blocking IO mode
 * @property bool eof TRUE if the stream has reached end-of-file.
 * @property int unread_bytes the number of bytes currently contained in the PHP's own internal buffer.
 * @property string stream_type a label describing the underlying implementation of the stream.
 * @property string wrapper_type a label describing the protocol wrapper implementation layered over the stream.
 * @property mixed wrapper_data wrapper specific data attached to this stream.
 * @property array filters array containing the names of any filters that have been stacked onto this stream.
 * @property string mode the type of access required for this stream
 * @property bool seekable whether the current stream can be seeked.
 * @property string uri the URI/filename associated with this stream.
 * @property source source of the stream.
 */
class StreamMetaData extends ArrayObject
{
    /**
     * Creates metadata object from stream
     *
     * @param resource $stream Instance of stream
     *
     * @throws \InvalidArgumentException for invalid stream
     */
    public function __construct($stream, $source = null)
    {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException("Stream should be valid resource");
        }
        $metadata = stream_get_meta_data($stream);
        if($source) {
            $metadata['source'] = $source;
        }
        parent::__construct($metadata, ArrayObject::ARRAY_AS_PROPS);
    }

    /**
     * Return original resource URI from filter URI
     *
     * @return string
     * @throws \InvalidArgumentException if resource was not found in URI
     */
    public function getResourceUri()
    {
        if (!preg_match('/resource=(.+)$/i', $this->uri, $matches)) {
            throw new InvalidArgumentException("Original resource not found in URI");
        }
        return $matches[1];
    }
}
