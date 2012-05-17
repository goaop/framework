<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Instrument\Transformer;

/**
 * @package go
 */
class AopProxyTransformer implements SourceTransformer
{

    /** Suffix, that will be added to all proxied class names */
    const AOP_PROXIED_SUFFIX = '__AopProxied';

    /**
     * This method may transform the supplied class file and return a new replacement class file
     *
     * @param string $source List of tokens for class
     *
     * @return string Transformed source
     */
    public function transform($source)
    {
    }

}
