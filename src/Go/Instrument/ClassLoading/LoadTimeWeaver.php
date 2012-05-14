<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Instrument\ClassLoading;

use Go\Instrument\ClassFileTransformer;

/**
 * @package go
 * @subpackage instrument
 */
interface LoadTimeWeaver
{
    /**
     * Adds a ClassFileTransformer to be applied by this LoadTimeWeaver.
     *
     * @param $transformer \Go\Instrument\ClassFileTransformer Transformer for source code
     * @return void
     */
    public static function addTransformer(ClassFileTransformer $transformer);
}
