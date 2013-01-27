<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2013, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Instrument\Transformer;

use Go\Core\AspectKernel;
use Go\Core\AspectContainer;

/**
 * @package go
 * @subpackage instrument
 */
abstract class BaseSourceTransformer implements SourceTransformer
{

    /**
     * Transformer options
     *
     * @var array
     */
    protected $options = array();

    /**
     * @var AspectKernel|null
     */
    protected $kernel = null;

    /**
     * @var AspectContainer|null
     */
    protected $container = null;

    /**
     * Default constructor for transformer
     *
     * @param AspectKernel $kernel Instance of aspect kernel
     * @param array $options Custom options or kernel options
     */
    public function __construct(AspectKernel $kernel, array $options = array())
    {
        $this->kernel    = $kernel;
        $this->container = $kernel->getContainer();
        $this->options   = $options ?: $kernel->getOptions();
    }
}