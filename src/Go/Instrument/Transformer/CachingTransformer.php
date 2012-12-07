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
     * Path to rewrite to (cache directory)
     *
     * @var string
     */
    protected $rewriteToPath = '';

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
        $this->rootPath      = realpath($options['appDir']);
        $this->rewriteToPath = realpath($options['cacheDir']);
        $this->transformers  = $transformers;
    }

    /**
     * This method may transform the supplied source and return a new replacement for it
     *
     * @param string $source Source for class
     * @param StreamMetaData $metadata Metadata for source
     *
     * @return string Transformed source
     */
    public function transform($source, StreamMetaData $metadata = null)
    {
        // Make the job only when we use cache directory
        if (!$this->rewriteToPath) {
            return $source;
        }

        $originalUri = $metadata->getResourceUri();
        $cacheUri    = stream_resolve_include_path($originalUri);
        $cacheUri    = str_replace($this->rootPath, $this->rewriteToPath, $cacheUri);

        // TODO: decide how to inject container in more friendly way
        $container     = AspectKernel::getInstance()->getContainer();
        $lastModified  = filemtime($originalUri);
        $cacheModified = file_exists($cacheUri) ? filemtime($cacheUri) : 0;

        // TODO: add more accurate cache invalidation, like in Symfony2
        if ($cacheModified < $lastModified || !$container->isFresh($cacheModified)) {
            @mkdir(dirname($cacheUri), 0770, true);
            foreach ($this->transformers as $transformer) {
                $source = $transformer->transform($source, $metadata);
            }
            file_put_contents($cacheUri, $source);
        } else {
            $source = file_get_contents($cacheUri);
        }

        return $source;
    }
}
