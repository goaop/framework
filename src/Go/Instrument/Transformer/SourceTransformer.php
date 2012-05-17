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
 * @subpackage instrument
 */
interface SourceTransformer
{

    /**
     * This method may transform the supplied source and return a new replacement for it
     *
     * @param string $source Source for class
     *
     * @return string Transformed source
     */
    public function transform($source);
}
