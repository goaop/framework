<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer;

use Go\Core\AspectKernel;
use Go\Core\AspectContainer;

/**
 * Base source transformer class definition
 */
abstract class BaseSourceTransformer implements SourceTransformer
{

    /**
     * Transformer options
     *
     * @var array
     */
    protected $options = [];

    /**
     * @var AspectKernel|null
     */
    protected $kernel;

    /**
     * @var AspectContainer|null
     */
    protected $container;

    /**
     * Default constructor for transformer
     *
     * @param AspectKernel $kernel Instance of aspect kernel
     * @param array $options Custom options or kernel options
     */
    public function __construct(AspectKernel $kernel, array $options = [])
    {
        $this->kernel    = $kernel;
        $this->container = $kernel->getContainer();
        $this->options   = $options ?: $kernel->getOptions();
    }
}
