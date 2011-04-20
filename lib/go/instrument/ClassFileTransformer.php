<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace go\instrument;

/**
 * @package go
 * @subpackage instrument
 */
interface ClassFileTransformer
{
    /**
     * This method may transform the supplied class file and return a new replacement class file
     *
     * @param string $className The name of the class to be transformed
     * @param array $classSourceTokens List of tokens for class
     * @return array Transformed list of tokens
     */
    public function transform($className, array $classSourceTokens);
}
