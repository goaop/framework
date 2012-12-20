<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Instrument\Transformer;

use Go\Core\AspectKernel;

/**
 * Take transformed source from the cache
 *
 * @package go
 * @subpackage instrument
 */
class CachingTransformer implements SourceTransformer
{

    /**
     * Root path of application
     *
     * @var string
     */
    protected $rootPath = '';

    /**
     * Cache directory
     *
     * @var string
     */
    protected $cachePath = '';

    /**
     * @var array|SourceTransformer[]
     */
    protected $transformers = array();

    /**
     * Class constructor
     *
     * @param array $options Configuration options from kernel
     * @param array $transformers Source transformers
     */
    public function __construct(array $options, array $transformers)
    {
        if ($options['cacheDir']) {
            $this->cachePath = realpath($options['cacheDir']);
        }

        $this->rootPath     = realpath($options['appDir']);
        $this->transformers = $transformers;
    }

    /**
     * This method may transform the supplied source and return a new replacement for it
     *
     * @param StreamMetaData $metadata Metadata for source
     * @return void
     */
    public function transform(StreamMetaData $metadata)
    {
        // Do not create a cache
        if (!$this->cachePath) {
            $this->processTransformers($metadata);
            return;
        }

        $originalUri = $metadata->getResourceUri();
        $cacheUri    = stream_resolve_include_path($originalUri);
        $cacheUri    = str_replace($this->rootPath, $this->cachePath, $cacheUri);

        // TODO: decide how to inject container in more friendly way
        $container     = AspectKernel::getInstance()->getContainer();
        $lastModified  = filemtime($originalUri);
        $cacheModified = file_exists($cacheUri) ? filemtime($cacheUri) : 0;

        // TODO: add more accurate cache invalidation, like in Symfony2
        if ($cacheModified < $lastModified || !$container->isFresh($cacheModified)) {
            @mkdir(dirname($cacheUri), 0770, true);
            $this->processTransformers($metadata);
            file_put_contents($cacheUri, $metadata->source);
            return;
        }
        $metadata->source = file_get_contents($cacheUri);
    }

    /**
     * Iterates over transformers
     *
     * @param StreamMetaData $metadata Metadata for source code
     * @return void
     */
    private function processTransformers(StreamMetaData $metadata)
    {
        foreach ($this->transformers as $transformer) {
            $transformer->transform($metadata);
        }
    }
}
