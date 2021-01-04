<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;

/**
 * Base source transformer class definition
 */
abstract class BaseSourceTransformer implements SourceTransformer
{
    /**
     * Transformer options
     */
    protected array $options = [];

    /**
     * Aspect kernel instance
     */
    protected AspectKernel $kernel;

    /**
     * Aspect container instance
     */
    protected AspectContainer $container;

    /**
     * Default constructor for transformer
     */
    public function __construct(AspectKernel $kernel, array $options = [])
    {
        $this->kernel    = $kernel;
        $this->container = $kernel->getContainer();
        $this->options   = $options ?: $kernel->getOptions();
    }
}
