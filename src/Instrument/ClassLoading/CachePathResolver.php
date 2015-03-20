<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\ClassLoading;
use Go\Core\AspectKernel;

/**
 * Class that manages real-code to cached-code paths mapping.
 * Can be extended to get a more sophisticated real-to-cached code mapping
 */
class CachePathResolver
{
    /**
     * @var array
     */
    protected $options = array();

    /**
     * @var \Go\Core\AspectKernel
     */
    protected $kernel;

    /**
     * @var string|null
     */
    protected $cacheDir;

    /**
     * @var string|null
     */
    protected $appDir;

    public function __construct (AspectKernel $kernel)
    {
        $this->kernel = $kernel;
        $this->options = $kernel->getOptions();
        $this->cacheDir = $this->options['cacheDir'];
        $this->appDir = $this->options['appDir'];
    }

    /**
     * @param string $resource
     * @return bool|string
     */
    public function getCachePathForResource ($resource)
    {
        if (!$this->cacheDir)
            return false;

        return str_replace($this->appDir, $this->cacheDir, $resource);
    }
}
